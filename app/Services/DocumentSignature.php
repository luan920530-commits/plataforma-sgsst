<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Documento;
use App\Models\User;
use App\Support\Audit;
use DomainException;
use RuntimeException;

/** Firma electrónica reforzada; no emite ni valida certificados digitales. */
final class DocumentSignature
{
    private const CONSENT = 'Confirmo mi identidad, revisé esta versión y consiento firmar electrónicamente el contenido identificado por su hash SHA-256.';

    public static function sign(int $documentId,int $tenantId,int $userId,string $password,mixed$consent):int
    {
        $document=Documento::findForTenant($documentId,$tenantId)?:throw new DomainException('Documento no encontrado.');
        if($document['estado']!=='Aprobado')throw new DomainException('Solo puede firmarse una versión aprobada. Firmar no sustituye la aprobación.');
        if($consent!=='1')throw new DomainException('Debe aceptar expresamente el consentimiento de firma.');
        $user=User::findForTenant($userId,$tenantId);if(!$user||!$user['activo']||!password_verify($password,$user['password']))throw new DomainException('La reautenticación no es válida. No se registró ninguna firma.');
        if(Database::first('SELECT id FROM documento_firmas WHERE tenant_id=? AND documento_id=? AND version=? AND user_id=?',[$tenantId,$documentId,$document['version'],$userId]))throw new DomainException('Esta cuenta ya firmó la versión actual.');
        $signedAt=gmdate('Y-m-d\TH:i:s\Z');$contentHash=hash('sha256',$document['contenido_html']);$key=self::key();
        $payload=['schema'=>'normia-document-signature-v1','tenant_id'=>$tenantId,'documento_id'=>$documentId,'codigo_documento'=>$document['codigo_estandar'],'version'=>(int)$document['version'],'contenido_sha256'=>$contentHash,'firmante'=>['id'=>$userId,'nombre'=>$user['nombre'],'email'=>text_lower($user['email']),'rol'=>$user['rol']],'consentimiento'=>self::CONSENT,'firmado_at'=>$signedAt,'key_id'=>substr(hash('sha256',$key),0,16)];
        $json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);$signatureHash=hash_hmac('sha256',$json,$key);
        return Database::transaction(static function()use($tenantId,$documentId,$userId,$user,$document,$json,$contentHash,$signatureHash,$signedAt):int{
            Database::execute('INSERT INTO documento_firmas (tenant_id,documento_id,documento_version_id,version,token,tipo,metodo,user_id,nombre_firmante,identificacion_firmante,consentimiento,contenido_hash,datos_firmados,firma_valor,algoritmo,ip_address,user_agent,firmado_at) VALUES (?,?,NULL,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$tenantId,$documentId,(int)$document['version'],bin2hex(random_bytes(32)),'electronica','password_consentimiento',$userId,$user['nombre'],text_lower($user['email']),self::CONSENT,$contentHash,$json,$signatureHash,'HMAC-SHA256; contenido SHA-256',substr((string)($_SERVER['REMOTE_ADDR']??'cli'),0,45),text_substr((string)($_SERVER['HTTP_USER_AGENT']??'cli'),0,250),$signedAt]);
            $signatureId=Database::lastId();
            Audit::record('Firma electrónica registrada','DocumentoFirma',$signatureId,['documento_id'=>$documentId,'version'=>(int)$document['version'],'contenido_sha256'=>$contentHash,'tipo'=>'electronica'],$tenantId,$userId);
            return $signatureId;
        });
    }

    public static function forDocument(int $documentId,int $tenantId,?int$version=null):array
    {
        $sql='SELECT f.*,u.nombre firmante_nombre,u.email firmante_email,u.rol firmante_rol,u.activo firmante_activo FROM documento_firmas f LEFT JOIN users u ON u.id=f.user_id WHERE f.documento_id=? AND f.tenant_id=?';$args=[$documentId,$tenantId];if($version!==null){$sql.=' AND f.version=?';$args[]=$version;}$sql.=' ORDER BY f.firmado_at DESC,f.id DESC';$rows=Database::query($sql,$args);foreach($rows as&$row)$row['verificacion']=self::verify((int)$row['id'],$tenantId);return$rows;
    }

    public static function verify(int $signatureId,int $tenantId):array
    {
        $signature=Database::first('SELECT * FROM documento_firmas WHERE id=? AND tenant_id=?',[$signatureId,$tenantId]);if(!$signature)throw new DomainException('Firma no encontrada.');
        if($signature['tipo']!=='electronica')return['valida'=>false,'mensaje'=>'Este registro no es una firma electrónica verificable por este servicio.','contenido_actual'=>false,'sello_integro'=>false,'vinculo'=>false];
        try{$payload=json_decode($signature['datos_firmados'],true,512,JSON_THROW_ON_ERROR);$key=self::key();}catch(\Throwable){return['valida'=>false,'mensaje'=>'No fue posible leer los datos o la clave de verificación.','contenido_actual'=>false,'sello_integro'=>false,'vinculo'=>false];}
        $seal=isset($payload['key_id'])&&hash_equals((string)$payload['key_id'],substr(hash('sha256',$key),0,16))&&hash_equals((string)$signature['firma_valor'],hash_hmac('sha256',(string)$signature['datos_firmados'],$key));
        $link=$seal&&($payload['schema']??null)==='normia-document-signature-v1'&&(int)($payload['tenant_id']??0)===$tenantId&&(int)($payload['documento_id']??0)===(int)$signature['documento_id']&&(int)($payload['version']??0)===(int)$signature['version']&&(int)($payload['firmante']['id']??0)===(int)$signature['user_id']&&hash_equals((string)($payload['contenido_sha256']??''),(string)$signature['contenido_hash']);
        $content=self::signedContent($signature);$contentValid=$content!==null&&hash_equals((string)$signature['contenido_hash'],hash('sha256',$content));$valid=$seal&&$link&&$contentValid;
        return['valida'=>$valid,'mensaje'=>$valid?'Integridad técnica verificada para la versión firmada.':'La firma no supera todas las comprobaciones de integridad.','contenido_actual'=>$contentValid,'sello_integro'=>$seal,'vinculo'=>$link,'key_id'=>$payload['key_id']??null];
    }

    public static function consentText():string{return self::CONSENT;}
    public static function contentHash(array$document):string{return hash('sha256',$document['contenido_html']);}

    private static function signedContent(array$signature):?string
    {
        if($signature['documento_version_id']){$version=Database::first('SELECT contenido_html FROM documento_versiones WHERE id=? AND documento_id=? AND tenant_id=? AND version=?',[$signature['documento_version_id'],$signature['documento_id'],$signature['tenant_id'],$signature['version']]);return$version['contenido_html']??null;}
        $document=Database::first('SELECT contenido_html,version FROM documentos_sgsst WHERE id=? AND tenant_id=?',[$signature['documento_id'],$signature['tenant_id']]);if(!$document)return null;if((int)$document['version']===(int)$signature['version'])return$document['contenido_html'];
        $version=Database::first('SELECT contenido_html FROM documento_versiones WHERE documento_id=? AND tenant_id=? AND version=? ORDER BY id DESC LIMIT 1',[$signature['documento_id'],$signature['tenant_id'],$signature['version']]);return$version['contenido_html']??null;
    }

    private static function key():string
    {
        $configured=(string)(getenv('APP_SIGNATURE_KEY')?:'');if($configured!==''){if(strlen($configured)<32)throw new RuntimeException('APP_SIGNATURE_KEY debe tener al menos 32 caracteres.');return$configured;}
        $directory=BASE_PATH.'/storage/security';if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory))throw new RuntimeException('No se pudo crear el directorio privado de firma.');$path=$directory.'/electronic-signing.key';if(!is_file($path)){$key=bin2hex(random_bytes(32));if(file_put_contents($path,$key,LOCK_EX)===false)throw new RuntimeException('No se pudo crear la clave privada de firma.');@chmod($path,0600);}
        $key=trim((string)file_get_contents($path));if(strlen($key)<32)throw new RuntimeException('La clave privada de firma no es válida.');return$key;
    }
}
