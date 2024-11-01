<?php

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once 'database.php';
require 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$db = new DataBase();
$con = $db->conectar();

// Definir los dominios permitidos para CORS
$dominiosPermitidos = ["http://localhost:3000", "https://darrona-pedidos.free.nf/"];

if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $dominiosPermitidos)) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header("Access-Control-Allow-Credentials: true");
}

header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Methods: OPTIONS, GET, POST, PUT, DELETE");
header('Content-Type: application/json');

// Manejar solicitudes OPTIONS (preflight)
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $dominiosPermitidos)) {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header("Access-Control-Allow-Credentials: true");
    }
    header('Access-Control-Allow-Methods: POST, GET, DELETE, PUT, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: token, Content-Type');
    header('Access-Control-Max-Age: 1728000');
    header('Content-Length: 0');
    header('Content-Type: text/plain');
    die();
}

function enviarRespuesta($data) {
    global $dominiosPermitidos;
    
    if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $dominiosPermitidos)) {
        header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        header("Access-Control-Allow-Credentials: true");
    }
    
    header('Content-Type: application/json');
    echo json_encode($data);
    
    exit;
}

if (isset($_GET['action'])) {
    $action = $_GET['action'];
    switch ($action) {
        case 'productos':
            $sql = $con->prepare("SELECT * FROM productos");
            $sql->execute();
            $resultado = $sql->fetchAll(PDO::FETCH_ASSOC);
            enviarRespuesta($resultado);
            break;

        case 'montominimo':
            $sql2 = $con->prepare("SELECT * FROM montominimo");
            $sql2->execute();
            $montos = $sql2->fetchAll(PDO::FETCH_ASSOC);
            enviarRespuesta($montos);
            break;

        case 'montos':
            $sql = $con->prepare("SELECT * FROM montos");
            $sql->execute();
            $montos = $sql->fetchAll(PDO::FETCH_ASSOC);
            enviarRespuesta($montos);
            break;

        case 'login':
            $sql3 = $con->prepare("SELECT * FROM logcred");
            $sql3->execute();
            $credenciales = $sql3->fetchAll(PDO::FETCH_ASSOC);
            enviarRespuesta($credenciales);
            break;

        case 'contact':
            $sql4 = $con->prepare("SELECT * FROM contacto");
            $sql4->execute();
            $contacto = $sql4->fetchAll(PDO::FETCH_ASSOC);
            enviarRespuesta($contacto);
            break;

        case 'shipments':
            $sql5 = $con->prepare("SELECT * FROM entregas");
            $sql5->execute();
            $entregas = $sql5->fetchAll(PDO::FETCH_ASSOC);
            enviarRespuesta($entregas);
            break;

        case 'orders':
            $sql6 = $con->prepare("SELECT * FROM pedidos");
            $sql6->execute();
            $pedidos = $sql6->fetchAll(PDO::FETCH_ASSOC);
            enviarRespuesta($pedidos);
            break;

        case 'delete-order':
            $data = json_decode(file_get_contents('php://input'), true);
            if (isset($data['id_pedido'])) {
                $orderId = intval($data['id_pedido']);
                $sql = $con->prepare('DELETE FROM pedidos WHERE id_pedido = :id_pedido');
                $sql->bindParam(':id_pedido', $orderId, PDO::PARAM_INT);
                if ($sql->execute()) {
                    enviarRespuesta(['success' => true]);
                } else {
                    enviarRespuesta(['success' => false, 'error' => 'Failed to delete order']);
                }
            } else {
                enviarRespuesta(['status' => 'error', 'message' => 'Datos incompletos']);
            }
            break;

        case 'update-seen-status':

            $data = json_decode(file_get_contents('php://input'), true);
        
            if (isset($data['id_pedido']) && isset($data['seen'])) {
                $orderId = intval($data['id_pedido']);
                $seenStatus = intval($data['seen']); // Convertir a entero ya que es un tinyint(1)
            
                $sql = "UPDATE pedidos SET visto ='$seenStatus' WHERE id_pedido = '$orderId'";
            
                if ($con->query($sql)) {
                    enviarRespuesta(['success' => true]);
                } else {
                    enviarRespuesta(['success' => false, 'error']);
                }
            } else {
                enviarRespuesta(['status' => 'error', 'message' => 'Datos incompletos']);
            }
            break;

        case 'save-order':
            $data = json_decode(file_get_contents("php://input"), true);
           
            if (isset($data['nombre_cliente'], $data['email_cliente'], $data['telefono_cliente'], $data['direccion_cliente'], $data['fecha_pedido'], $data['detalle'], $data['total_pedido'], $data['visto'], $data['tipo_comprador'], $data['email'], $data['telefono'])) {
                $nombre_cliente = $data['nombre_cliente'];
                $email_cliente = $data['email_cliente'];
                $telefono_cliente = $data['telefono_cliente'];
                $direccion_cliente = $data['direccion_cliente'];
                $fecha_pedido = $data['fecha_pedido'];
                $detalle = $data['detalle'];
                $total_pedido = floatval($data['total_pedido']);
                $visto = $data['visto'];
                $comprador = $data['tipo_comprador'];
                $email = $data['email'];
                $telefono = $data['telefono'];

                $sql = $con->prepare("INSERT INTO pedidos (nombre_cliente, email_cliente, telefono_cliente, direccion_cliente, fecha_pedido, detalle, total, visto, tipo_comprador) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $sql->execute([$nombre_cliente, $email_cliente, $telefono_cliente, $direccion_cliente, $fecha_pedido, $detalle, $total_pedido, $visto, $comprador]);

                
                if ($sql->rowCount() > 0) {
                    try {
                        //Email Darrona
                        $mail = new PHPMailer(true);

                        // Configuración del servidor
                        $mail->isSMTP();
                        $mail->Host = 'smtp.gmail.com';
                        $mail->SMTPAuth = true;
                        $mail->Username = 'pedidodarrona@gmail.com';
                        $mail->Password = 'kjqpdlybcfuvglhk';
                        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = 587;

                        $mail->setFrom('pedidodarrona@gmail.com', 'Diatribuidora Darrona');
                        // $mail->addAddress('ad.darrona@gmail.com');
                        $mail->addAddress('nataliagiselle.c@gmail.com');

                        // Contenido
                        $mail->isHTML(true);
                        $mail->Subject = 'Nuevo pedido de compra';
                        $detalleArray = json_decode($detalle, true);
                        $detalleHTML = '';
                        foreach ($detalleArray as $item) {
                            $product = $item['product'];
                            $detalleHTML .= '<li>' . htmlspecialchars($product['Producto']) . ' - Cantidad: ' . htmlspecialchars($item['quantity']) . ' - Total: $' . number_format($item['totalProduct'], 2, ',', '.') . '</li>';
                        }

                        // Load HTML template for Darrona
                        $htmlDarrona = file_get_contents('darrona-email.html');

                        // Replace placeholders for Darrona
                        $htmlDarrona = str_replace('{{nombre_cliente}}', htmlspecialchars($nombre_cliente), $htmlDarrona);
                        $htmlDarrona = str_replace('{{email_cliente}}', htmlspecialchars($email_cliente), $htmlDarrona);
                        $htmlDarrona = str_replace('{{telefono_cliente}}', htmlspecialchars($telefono_cliente), $htmlDarrona);
                        $htmlDarrona = str_replace('{{direccion_cliente}}', htmlspecialchars($direccion_cliente), $htmlDarrona);
                        $htmlDarrona = str_replace('{{fecha_pedido}}', htmlspecialchars($fecha_pedido), $htmlDarrona);
                        $htmlDarrona = str_replace('{{total_pedido}}', number_format($total_pedido, 2, ',', '.'), $htmlDarrona);
                        $htmlDarrona = str_replace('{{detalle_pedido}}', $detalleHTML, $htmlDarrona);

                        $mail->Body = $htmlDarrona;
                        $mail->AltBody = 'Hola Darrona,\n\nAcabas de recibir un nuevo pedido de compra de: ' . htmlspecialchars($nombre_cliente) . '.\n\nSaludos,\nTu tienda en línea';

                        $mail->send();

                        //Email Cliente
                         // Configuración del servidor
                        $mailCliente = new PHPMailer(true);
                        $mailCliente->isSMTP();
                        $mailCliente->Host = 'smtp.gmail.com';
                        $mailCliente->SMTPAuth = true;
                        $mailCliente->Username = 'pedidodarrona@gmail.com';
                        $mailCliente->Password = 'kjqpdlybcfuvglhk';
                        $mailCliente->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                        $mailCliente->Port = 587;

                        $mailCliente->setFrom('pedidodarrona@gmail.com', 'Diatribuidora Darrona');
                        $mailCliente->addAddress($email_cliente);

                        $mailCliente->isHTML(true);
                        $mailCliente->Subject = 'Pedido Darrona';
                        $detalleArray = json_decode($detalle, true);
                        $detalleHTMLcliente = '';
                        foreach ($detalleArray as $item) {
                            $product = $item['product'];
                            $detalleHTMLcliente .= '<li>' . htmlspecialchars($product['Producto']) . ' - Cantidad: ' . htmlspecialchars($item['quantity']) . ' - Total: $' . number_format($item['totalProduct'], 2, ',', '.') . '</li>';
                        }

                        // Load HTML template
                        $htmlCliente = file_get_contents('client-email.html');

                         // Replace placeholders for Cliente
                        $htmlCliente = str_replace('{{nombre_cliente}}', htmlspecialchars($nombre_cliente), $htmlCliente);
                        $htmlCliente = str_replace('{{email_cliente}}', htmlspecialchars($email_cliente), $htmlCliente);
                        $htmlCliente = str_replace('{{telefono_cliente}}', htmlspecialchars($telefono_cliente), $htmlCliente);
                        $htmlCliente = str_replace('{{direccion_cliente}}', htmlspecialchars($direccion_cliente), $htmlCliente);
                        $htmlCliente = str_replace('{{total_pedido}}', number_format($total_pedido, 2, ',', '.'), $htmlCliente);
                        $htmlCliente = str_replace('{{detalle_pedido}}', $detalleHTML, $htmlCliente);
                        $htmlCliente = str_replace('{{telefono}}', htmlspecialchars($telefono), $htmlCliente);

                        // Content
                        $mailCliente->isHTML(true);
                        $mailCliente->CharSet = 'UTF-8';
                        $mailCliente->Subject = 'Confirmación de Pedido';
                        $mailCliente->Body = $htmlCliente;
                        $mailCliente->AltBody = 'Hola ' . htmlspecialchars($nombre_cliente) . ',\n\nGracias por tu compra. Hemos recibido tu pedido y estamos procesándolo. Aquí tienes los detalles de tu pedido:\n\nNombre: ' . htmlspecialchars($nombre_cliente) . '\nEmail: ' . htmlspecialchars($email_cliente) . '\nTeléfono: ' . htmlspecialchars($telefono_cliente) . '\nDirección: ' . htmlspecialchars($direccion_cliente) . '\nFecha del pedido: ' . htmlspecialchars($fecha_pedido) . '\nTotal del pedido: $' . number_format($total_pedido, 2, ',', '.') . '\n\nSaludos,\nTu tienda en línea';

                        $mailCliente->send();

                         enviarRespuesta(['success' => 'Pedido guardado y correo enviado exitosamente']);
                    } catch (Exception $e) {
                         enviarRespuesta(['error' => "El correo no pudo ser enviado. Error de PHPMailer: {$mail->ErrorInfo}"]);
                    }
                } else {
                     enviarRespuesta(['error' => 'Error al guardar el pedido']);
                }

            } else {
                 enviarRespuesta(['error' => 'Datos incompletos']);
            }

            break;

        case 'ping':
            echo json_encode(['status' => 'ok', 'message' => 'Servidor en funcionamiento']);
            exit;

            break;

        default:
             enviarRespuesta(['error' => 'acción no válida']);
            break;
    }
} else {
     enviarRespuesta(['error' => 'acción no válida']);
}
