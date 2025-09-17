<?php
/**
 * Script temporal para crear base.jpg
 * Este archivo se debe ejecutar una sola vez para crear la imagen faltante
 */

// Crear una imagen de 800x600 píxeles con fondo blanco
$width = 800;
$height = 600;

// Crear imagen
$image = imagecreate($width, $height);

// Definir colores
$white = imagecolorallocate($image, 248, 249, 250); // Color de fondo similar a Bootstrap
$lightgray = imagecolorallocate($image, 233, 236, 239);

// Rellenar fondo
imagefill($image, 0, 0, $white);

// Agregar algunos elementos decorativos sutiles
// Líneas sutiles para dar textura
for ($i = 0; $i < $height; $i += 20) {
    imageline($image, 0, $i, $width, $i, $lightgray);
}

for ($i = 0; $i < $width; $i += 20) {
    imageline($image, $i, 0, $i, $height, $lightgray);
}

// Guardar imagen como JPEG
$filename = '/tmp/base.jpg';
imagejpeg($image, $filename, 90);

// Limpiar memoria
imagedestroy($image);

echo "Imagen base.jpg creada en: $filename\n";
echo "Tamaño del archivo: " . filesize($filename) . " bytes\n";
?>