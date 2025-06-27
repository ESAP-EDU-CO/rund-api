#!/bin/sh

# Asignar la propiedad de los archivos al usuario y grupo de Apache ('www-data')
# Esto es crucial para que el servidor web pueda leer los archivos y escribir en directorios si es necesario.
chown -R www-data:www-data /var/www/html

# Asignar permisos adecuados a los archivos y directorios.
chmod -R 755 /var/www/html

# Iniciar Apache en primer plano (foreground).
# Es fundamental que el comando principal se ejecute en primer plano para que el contenedor no se detenga.
apache2-foreground
