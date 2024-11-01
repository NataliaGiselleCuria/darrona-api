<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'database.php';

$db = new DataBase();
$con = $db->conectar();

$dominiosPermitidos = [
    "https://localhost:5173"
];

header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, DELETE");
header("Access-Control-Allow-Credentials: true");
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: token, Content-Type');
    header('Access-Control-Max-Age: 1728000');
    header('Content-Length: 0');
    header('Content-Type: text/plain');
    die();
}

header('Access-Control-Allow-Origin: *');

function enviarRespuesta($data)
{
    global $dominiosPermitidos;
    if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $dominiosPermitidos)) {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    }
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

$directorioDescarga = "descargas/";
$uploadOk = 1;

try {
    if (isset($_FILES["fileInput"]) && !empty($_FILES["fileInput"]["name"])) {
        $targetFile = $directorioDescarga . basename($_FILES["fileInput"]["name"]);
        $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

        if ($fileType != "csv") {
            enviarRespuesta(["status" => "error", "message" => "Solo pueden subirse archivos en formato CSV."]);
            $uploadOk = 0;
        }

        if ($uploadOk === 1) {
            $truncateQuery = "TRUNCATE TABLE productos";
            if ($con->query($truncateQuery)) {
                // Continuar
            } else {
                enviarRespuesta(["status" => "error", "message" => 'Error al limpiar la tabla: ' . $con->errorInfo()[2]]);
            }

            $fileName = $_FILES['fileInput']['name'];
            $tempFilePath = $_FILES['fileInput']['tmp_name'];
            $targetFilePath = $directorioDescarga . $fileName;

            // Mover el archivo a la ubicación de destino
            move_uploaded_file($tempFilePath, $targetFilePath);

            // Reabrir el archivo convertido para procesarlo
            $csvFile = fopen($targetFilePath, 'r');

            // Omitir la primera línea de encabezado
            fgetcsv($csvFile);

            $sql = $con->prepare(
                "INSERT INTO productos (
                    `Código`,
                    `Producto`,
                    `Categoría`,
                    `Presentación`,
                    `Cantidad x pres.`,
                    `Peso`,
                    `minorista precio x presentación`,
                    `mayorista precio x presentación`,
                    `distribuidor precio x presentación`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );

            $lineNumber = 1;

            while (($data = fgetcsv($csvFile, 1000, ";")) !== false) {
                $lineNumber++;
                $data = array_map('trim', $data);
                $data = array_filter($data, fn($value) => !is_null($value) && $value !== '');

                $columnCount = count($data);
                json_encode($columnCount);

                if (count($data) === 9) {
                    $sql->execute($data);
                } else {

                    $message = "La fila $lineNumber tiene " . count($data) . " columnas en lugar de las 9 requeridas.\n\nContenido de la fila:\n " . implode(", ", $data). ".";

                    if (count($data) < 9) {
                       
                        if (count($data) === 1){
                            $message .= " \n\nSi la fila contiene una sola columna el problema puede estar en el delimitador. Recordar que el formato CSV debe delimitar las casilas con el signo ';'";
                        }

                        $message .= " \n\nPuede haber casillas vacías. Por favor, revise que todas las columnas tengan datos.\nGuarde y vuelva a cargar el archivo.";

                    } else if (count($data) > 9) {
                        $message .= " \n\nEl archivo parece tener columnas adicionales. Por favor, revise el delimitador o elimine columnas extra.\nGuarde y vuelva a cargar el archivo.";

                    }

                    enviarRespuesta([
                        "status" => "error",
                        "message" => $message
                    ]);

                    exit;
                }
            }

            fclose($csvFile);
            enviarRespuesta(["status" => "success", "message" => "Archivo CSV cargado exitosamente."]);
        } else {
            enviarRespuesta(["status" => "error", "message" => 'Fallo en la carga del archivo.']);
        }
    } else {
        enviarRespuesta(["status" => "error", "message" => "No se ha proporcionado ningún archivo."]);
    }
} catch (PDOException $e) {
    enviarRespuesta(["status" => "error", "message" => "Error al cargar el archivo CSV: " . $e->getMessage()]);
}
?>

