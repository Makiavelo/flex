mkdir tmp
cp -r src tmp/src
mkdir tmp/vendor
cp -r vendor/composer tmp/vendor
cp vendor/autoload.php tmp/vendor/autoload.php
cp composer.json tmp/composer.json
cd tmp
composer install --no-dev --no-interaction -q
rm composer.json
rm composer.lock
cd ..
php util/build_phar.php --name="flex.phar" --from="./tmp" --stub="vendor/autoload.php"
mv flex.phar phar/flex.phar
rm -rf tmp
