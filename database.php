<?php
    class DataBase{

        // private $hostname = "sql311.infinityfree.com";
        // private $database = "if0_36829084_darrona";
        // private $username = "if0_36829084";
        // private $password = "QjeYaAMQUy";
        // private $charset = "utf8";

        private $hostname = "localhost";
        private $database = "darrona";
        private $username = "root";
        private $password = "";
        private $charset = "utf8";

        function conectar(){

            try{

            $conextion = "mysql:host=" . $this->hostname . "; dbname=" . $this->database . "; charset=" . $this->charset;

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_LOCAL_INFILE => true
            ];

            $pdo = new PDO($conextion, $this->username, $this->password, $options);

            return $pdo;

            } catch(PDOException $e) {

                echo 'Error de conexión: ' . $e->getMessage();
                exit;
            }
        }
    }


?>
