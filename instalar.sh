#!/bin/bash
# Instalador para Mac/Linux: se ubica solo en la raíz del proyecto y corre setup.php.
# Uso:  bash instalar.sh
cd "$(dirname "$0")" || exit 1

if [ -x /Applications/XAMPP/xamppfiles/bin/php ]; then
  PHP=/Applications/XAMPP/xamppfiles/bin/php   # XAMPP en Mac
elif [ -x /opt/lampp/bin/php ]; then
  PHP=/opt/lampp/bin/php                      # XAMPP en Linux
elif command -v php >/dev/null 2>&1; then
  PHP=php
else
  echo "❌ No encontré PHP. Instala XAMPP (https://www.apachefriends.org) e inténtalo de nuevo."
  exit 1
fi

echo "📂 Proyecto: $(pwd)"
echo "🐘 PHP: $PHP"
"$PHP" setup.php
