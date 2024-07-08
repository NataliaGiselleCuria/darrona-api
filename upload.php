<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'database.php';

$db = new DataBase();
$con = $db->conectar();

$dominiosPermitidos = [
    "https://localhost:5173"
];

// if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $dominiosPermitidos)) {
//     header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
// }

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
header('Content-Type: application/json');



$directorioDescarga = "descargas/";
$uploadOk = 1;

try{
    if (isset($_FILES["fileInput"]) && !empty($_FILES["fileInput"]["name"])) {
        $targetFile = $directorioDescarga . basename($_FILES["fileInput"]["name"]);
        $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    
        if ($fileType != "csv") {
            echo "Solo pueden subirse archivos en formato CSV.";
            $uploadOk = 0;
        } else {
            if ($uploadOk == 0) {
                echo 'Fallo en la carga del archivo.';
            } else {
    
                $truncateQuery = "TRUNCATE TABLE productos";
                if ($con->query($truncateQuery)) {
    
                } else {
                    echo ' Error al limpiar la tabla: ' . $con->errorInfo()[2];
                    exit;
                }
    
                $fileName = $_FILES['fileInput']['name'];
                $tempFilePath = $_FILES['fileInput']['tmp_name'];
    
                $targetFilePath = $directorioDescarga . $fileName;
                move_uploaded_file($tempFilePath, $targetFilePath);
    
                $csvFile = fopen($targetFilePath, 'r');
    
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
                    $lineNumber++;
                    
                    // Convertir a UTF-8
                    // $data = array_map('utf8_encode', $data);
                    $data = array_map('trim', $data);
                    $data = array_filter($data, fn($value) => !is_null($value) && $value !== '');

                    if (count($data) === 9) {
                        $sql->execute($data);
                    } else {
                        echo json_encode([
                            "status" => "error",
                            "message" => "\nLa fila $lineNumber no tiene el número correcto de elementos.\nContenido de la fila:\n " . implode(", ", $data) . "\n\nPor favor, revise su archivo de productos, no deve contener casillas vacías. Guarde y vuelvas a cargar el archivo."
                        ]);
                        exit;
                    }
                }

                fclose($csvFile);
                echo enviarRespuesta(["status" => "success", "message" => "Archivo CSV cargado exitosamente."]);
            }
        }
    }else {
        echo enviarRespuesta(["status" => "error", "message" => "No se ha proporcionado ningún archivo."]);
    }
}catch (PDOException $e) {
    if ($e->getCode() == 'HY093') {
        echo enviarRespuesta(["status" => "error", "message" => "Error en la carga: Revise que el archivo CSV tenga las columnas correctas."]);
    } else {
        echo enviarRespuesta(["status" => "error", "message" => "Error al cargar el archivo CSV: " . $e->getMessage()]);
    }
} catch (Exception $e) {
    echo enviarRespuesta(["status" => "error", "message" => $e->getMessage()]);
}

?>
