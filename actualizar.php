<?php
error_reporting(E_ALL);
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


if (isset($_GET['action'])) {
    $action = $_GET['action'];
    switch ($action) {
        case 'cred':

            $input = json_decode(file_get_contents('php://input'), true);

            if (isset($input['usuario']) && isset($input['clave'])) {
                $nuevoUsuario = $input['usuario'];
                $nuevaClave = $input['clave'];

                $sql = "UPDATE `logcred` SET `usuario`='$nuevoUsuario', `clave`= sha2('$nuevaClave',256) WHERE `id`=1";

                if ($con->query($sql)) {
                    enviarRespuesta(['success' => true]);
                } else {
                    enviarRespuesta(['success' => false, 'error']);
                }
            } else {
                enviarRespuesta(['success' => false, 'error' => 'Invalid input']);
            }

            break;
        case 'amounts':
            $input = json_decode(file_get_contents('php://input'), true);

            if (isset($input['updatedAmounts']) && isset($input['updatedAmountsValues'])) {
                $updatedAmounts = $input['updatedAmounts'];
                $updatedAmountsValues = $input['updatedAmountsValues'];

                // Actualizar mensajes en la tabla montominimo
                $nuevoMensajeMinorista = $updatedAmounts['minorista'] ?? $currentValues['minorista'];
                $nuevoMensajeMayorista = $updatedAmounts['mayorista'] ?? $currentValues['mayorista'];
                $nuevoMensajeDistribuidor = $updatedAmounts['distribuidor'] ?? $currentValues['distribuidor'];

                $sql2 = "UPDATE `montominimo` SET
                            `mensaje` = CASE
                            WHEN `categoría` = 'minorista' THEN :minorista
                            WHEN `categoría` = 'mayorista' THEN :mayorista
                            WHEN `categoría` = 'distribuidor' THEN :distribuidor
                            ELSE mensaje
                            END";

                $stmt = $con->prepare($sql2);
                $stmt->bindParam(':minorista', $nuevoMensajeMinorista);
                $stmt->bindParam(':mayorista', $nuevoMensajeMayorista);
                $stmt->bindParam(':distribuidor', $nuevoMensajeDistribuidor);

                // Verificar si la consulta de actualización de montominimo se ejecuta correctamente
                $success1 = $stmt->execute();

                // Actualizar valores en la tabla montos
                $minoristaMin = $updatedAmountsValues['minoristaMin'] ?? $currentValues['minoristaMin'];
                $minoristaMax = $updatedAmountsValues['minoristaMax'] ?? $currentValues['minoristaMax'];
                $mayoristaMin = $updatedAmountsValues['mayoristaMin'] ?? $currentValues['mayoristaMin'];
                $mayoristaMax = $updatedAmountsValues['mayoristaMax'] ?? $currentValues['mayoristaMax'];
                $distribuidorMin = $updatedAmountsValues['distribuidorMin'] ?? $currentValues['distribuidorMin'];
                $distribuidorMax = $updatedAmountsValues['distribuidorMax'] ?? $currentValues['distribuidorMax'];

                $sql3 = "UPDATE `montos` SET
                            `minimo` = CASE
                            WHEN `categoría` = 'minorista' THEN :minoristaMin
                            WHEN `categoría` = 'mayorista' THEN :mayoristaMin
                            WHEN `categoría` = 'distribuidor' THEN :distribuidorMin
                            ELSE minimo
                            END,
                            `maximo` = CASE
                            WHEN `categoría` = 'minorista' THEN :minoristaMax
                            WHEN `categoría` = 'mayorista' THEN :mayoristaMax
                            WHEN `categoría` = 'distribuidor' THEN :distribuidorMax
                            ELSE maximo
                            END";

                $stmt2 = $con->prepare($sql3);
                $stmt2->bindParam(':minoristaMin', $minoristaMin);
                $stmt2->bindParam(':minoristaMax', $minoristaMax);
                $stmt2->bindParam(':mayoristaMin', $mayoristaMin);
                $stmt2->bindParam(':mayoristaMax', $mayoristaMax);
                $stmt2->bindParam(':distribuidorMin', $distribuidorMin);
                $stmt2->bindParam(':distribuidorMax', $distribuidorMax);

                // Verificar si la consulta de actualización de montos se ejecuta correctamente
                $success2 = $stmt2->execute();

                if ($success1 && $success2) {
                    enviarRespuesta(['success' => true]);
                } else {
                    enviarRespuesta(['success' => false, 'error' => 'Error al actualizar los datos']);
                }
            } else {
                enviarRespuesta(['success' => false, 'error' => 'Invalid input']);
            }
            break;
        case 'contact':
            $input = json_decode(file_get_contents('php://input'), true);

            if (isset($input['direccion']) || isset($input['telefono']) || isset($input['email']) || isset($input['entrega'])) {
                $direccion = $input['direccion'] ?? $currentValues['direccion'];
                $telefono = $input['telefono'] ?? $currentValues['telefono'];
                $email = $input['email'] ?? $currentValues['email'];
                $entrega = $input['entrega'] ?? $currentValues['entrega'];

                // Consulta SQL para actualizar los datos de contacto
                $sql = "UPDATE `contacto` SET
                                `valor` = CASE
                                WHEN `nombre` = 'direccion' THEN :direccion
                                WHEN `nombre` = 'telefono' THEN :telefono
                                WHEN `nombre` = 'email' THEN :email
                                WHEN `nombre` = 'entrega' THEN :entrega
                                ELSE valor
                                END
                            WHERE `nombre` IN ('direccion', 'telefono', 'email', 'entrega')";

                $stmt = $con->prepare($sql);
                $stmt->bindParam(':direccion', $direccion);
                $stmt->bindParam(':telefono', $telefono);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':entrega', $entrega);

                // Verificar si la consulta de actualización de contacto se ejecuta correctamente
                $success = $stmt->execute();

                if ($success) {
                    enviarRespuesta(['success' => true]);
                } else {
                    enviarRespuesta(['success' => false, 'error' => 'Error al actualizar los datos de contacto']);
                }
            } else {
                enviarRespuesta(['success' => false, 'error' => 'Invalid input']);
            }
            break;
        case 'shipments':
            $input = json_decode(file_get_contents('php://input'), true);

            if (isset($_GET['action']) && $_GET['action'] === 'shipments') {
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    try {
                        // Iniciar una transacción
                        $con->beginTransaction();

                        // Limpiar la tabla de envíos actual
                        $con->exec("DELETE FROM entregas");

                        // Preparar la consulta de inserción
                        $stmt = $con->prepare("INSERT INTO entregas (lugar, dia) VALUES (:lugar, :dia)");

                        // Insertar cada envío
                        foreach ($input as $shipment) {
                            $stmt->bindParam(':lugar', $shipment['lugar']);
                            $stmt->bindParam(':dia', $shipment['dia']);
                            $stmt->execute();
                        }

                        // Confirmar la transacción
                        $con->commit();
                        enviarRespuesta(['success' => true]);
                    } catch (Exception $e) {
                        // Revertir la transacción en caso de error
                        $con->rollBack();
                        enviarRespuesta(['success' => false, 'error' => $e->getMessage()]);
                    }
                }
            } else {
                enviarRespuesta(['success' => false, 'error' => 'Invalid action']);
            }
        default:
            enviarRespuesta(['success' => false, 'error' => 'Invalid action']);
            break;
    }
}
