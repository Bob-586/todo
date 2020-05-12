#!/usr/bin/php -q
<?php

function is_cli() {
    if (defined('STDIN')) {
        return true;
    }

    if (php_sapi_name() === 'cli') {
        return true;
    }

    if (array_key_exists('SHELL', $_ENV)) {
        return true;
    }

    if (empty($_SERVER['REMOTE_ADDR']) and ! isset($_SERVER['HTTP_USER_AGENT']) and count($_SERVER['argv']) > 0) {
        return true;
    }

    if (!array_key_exists('REQUEST_METHOD', $_SERVER)) {
        return true;
    }

    return false;
}

if (is_cli() === false) {
    echo('Unable to Start');
    exit(1);
}

$todo_file = "todo.db";
if (posix_getuid() > 0) {
   //$u = getenv("USERNAME");
   //$home_dir = "/home/{$u}/.todo";
   $home_dir = $_SERVER['HOME'] . "/.todo";
} else {
   $home_dir = "/root/.todo";    
}

if (! is_dir($home_dir)) {
    $s = mkdir($home_dir);
    if ($s === false) {
	echo "Unable to create folder: {$home_dir}" . PHP_EOL;
        exit(1);
    }
}

try {
    $pdo = new PDO("sqlite:{$home_dir}/{$todo_file}");
} catch (PDOException $e) {
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}

try {
    $sql = "CREATE TABLE IF NOT EXISTS items (
            id   INTEGER PRIMARY KEY AUTOINCREMENT,
            item  TEXT    NOT NULL,
            nonce TEXT    NULL,    
            completed INTEGER
      );";
    $pdo->query($sql);
} catch (\PDOException $e) {
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}

try {
    $sql = "CREATE TABLE IF NOT EXISTS password (
            id   INTEGER PRIMARY KEY AUTOINCREMENT,
            mykey  TEXT    NOT NULL,
            myhash TEXT    NOT NULL
      );";
    $pdo->query($sql);
} catch (\PDOException $e) {
    echo $e->getMessage() . PHP_EOL;
    exit(1);
}

$command = $argv[1] ?? "ls";
$A = $argv[2] ?? "";
$B = $argv[3] ?? "";

function get_status($status) {
    switch(strtolower($status)) {
        case "done": case "complete": $status = "1"; break;
        default: $status = "0"; break;    
    }
    return $status;
}

function get_id($id) {
    $success = settype($id, "integer");
    if ($success === false) {
        exit(1);
    }
    return $id;
}

switch(strtolower($command)) {
   case "help": case "?": $action = "help"; break; 
   case "add": 
       $action = "add";
       $item = $A;
       $status = get_status($B);
       break;
   case "remove": case "rm": 
       $action = "rm";
       $id = get_id($A);
       break;
   case "update": 
       $action = "update";
       $id = get_id($A);
       $item = $B;
       break;
   case "complete": case "done": 
       $action = "complete"; 
       $id = get_id($A);
       break;
   case "incomplete": case "not-done": 
       $action = "incomplete"; 
       $id = get_id($A);
       break;
   default: $action = "ls"; break;
}

if ($action === "help") {
    echo "To list: ls" . PHP_EOL;
    echo "To add: add \"Item Info\" incomplete" . PHP_EOL;
    echo "To remove: rm Item#" . PHP_EOL;
    echo "To update: update Item# \"Updated item info\"" . PHP_EOL;
    echo "To mark as complete: complete Item#" . PHP_EOL;
    echo "To mark as incomplete: incomplete Item#" . PHP_EOL;
    exit(0);
}

try {
    require 'crypto.php';
    $c = new \todo\encryption\crypto();

    $sql = "SELECT COUNT(id) AS c FROM password";
    $pdostmt = $pdo->prepare($sql);
    $pdostmt->execute();
    $count = $pdostmt->fetch(PDO::FETCH_COLUMN);
    if (intval($count) == 0) {
       echo "Create a password: ";
       $pwd = rtrim( shell_exec("/bin/bash -c 'read -s PW; echo \$PW'") );
       if (empty($pwd)) {
         $sql = "INSERT INTO password (myhash, mykey) VALUES ('none', '')";
         $pdostmt = $pdo->prepare($sql);
         if (! $pdostmt === false) {
            $pdostmt->execute();
         }
         $do_encode = false;
       } else {
         $sql = "INSERT INTO password (myhash, mykey) VALUES (:hash, :key)";
         $pdostmt = $pdo->prepare($sql);
         if (! $pdostmt === false) {
            $myhash = password_hash($pwd, PASSWORD_BCRYPT);
            $key = $c->getKey();
            $ekey = openssl_encrypt($key, "AES-128-ECB", $pwd);
            $pdostmt->execute(["hash"=>$myhash, "key"=>$ekey]);
         }
         $do_encode = true;
       }
    } else {
        $sql = "SELECT myhash, mykey FROM password WHERE id=1 LIMIT 1";
        $pdostmt = $pdo->prepare($sql);
        $pdostmt->execute();
        $row = $pdostmt->fetch(\PDO::FETCH_ASSOC);
        $myhash = $row['myhash'];
        if ($myhash === "none") {
            $do_encode = false;
        } else {
            $do_encode = true;
            echo "Enter password: ";
            $pwd = rtrim( shell_exec("/bin/bash -c 'read -s PW; echo \$PW'") );
            if (! password_verify($pwd, $myhash)) {
                echo "Invalid Password!" . PHP_EOL;
                exit(1);
            }
            $key = openssl_decrypt($row['mykey'], "AES-128-ECB", $pwd);
        }
    }
} catch (\Exception $ex) {
    echo $ex->getMessage();
    exit(1);    
} catch (\PDOException $e) {
    echo $e->getMessage();
    exit(1);
}

echo PHP_EOL;

if ($action === "ls") {
    try {
        $sql = "SELECT id, item, nonce, completed FROM items ORDER BY id ASC";
        $pdostmt = $pdo->prepare($sql);
        if ($pdostmt === false) {
           echo "INVALID Schema!";
           exit(1);
        }
        $pdostmt->execute();
        $rows = $pdostmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as $row) {
            $done = ($row['completed'] == 1) ? "Complete" : "Incomplete";
            if ($do_encode) {
                $c->setNonce($row['nonce']);
                $item = $c->decode($key, $row['item']);
            } else {
                $item = $row['item'];
            }
            echo "[{$row['id']}] {$done} - $item" . PHP_EOL;
        }
    } catch (\PDOException $e) {
        echo $e->getMessage();
        exit(1);
    }
    exit(0);
}

if ($action === "add") {
    try {
        $sql = "INSERT INTO items (item, nonce, completed) VALUES (:item, :nonce, :completed)";
        $pdostmt = $pdo->prepare($sql);
        if (! $pdostmt === false) {
            if ($do_encode) {
                $nonce = $c->getNonce();
                $enc_item = $c->encode($key, $item);
            } else {
                $nonce = "";
                $enc_item = $item;
            }
            $pdostmt->execute(["item"=>$enc_item, "nonce"=>$nonce, "completed"=>$status]);
        }
    } catch (\Exception $ex) {
        echo $ex->getMessage();
        exit(1);
    } catch (\PDOException $e) {
        echo $e->getMessage();
        exit(1);
    }
    exit(0);    
}

if ($action === "rm") {
    try {
        $sql = "DELETE FROM items WHERE id=:id LIMIT 1";
        $pdostmt = $pdo->prepare($sql);
        if (! $pdostmt === false) {
            $pdostmt->execute(["id"=>$id]);
        }
    } catch (\PDOException $e) {
        echo $e->getMessage();
	exit(1);
    }
    exit(0);    
}


if ($action === "update") {
    try {
        $sql = "UPDATE items SET item=:item, nonce=:nonce WHERE id=:id LIMIT 1";
        $pdostmt = $pdo->prepare($sql);
        if (! $pdostmt === false) {
            if ($do_encode) {
                $nonce = $c->getNonce();
                $enc_item = $c->encode($key, $item);
            } else {
                $nonce = "";
                $enc_item = $item;
            }
            $pdostmt->execute(["item"=>$enc_item, "nonce"=>$nonce, "id"=>$id]);
        }
    } catch (\PDOException $e) {
        echo $e->getMessage();
        exit(1);
    }
    exit(0);    
}

if ($action === "complete") {
    try {
        $sql = "UPDATE items SET completed='1' WHERE id=:id LIMIT 1";
        $pdostmt = $pdo->prepare($sql);
        if (! $pdostmt === false) {
            $pdostmt->execute(["id"=>$id]);
        }
    } catch (\PDOException $e) {
        echo $e->getMessage();
        exit(1);
    }
    exit(0);    
}

if ($action === "incomplete") {
    try {
        $sql = "UPDATE items SET completed='0' WHERE id=:id LIMIT 1";
        $pdostmt = $pdo->prepare($sql);
        if (! $pdostmt === false) {
            $pdostmt->execute(["id"=>$id]);
        }
    } catch (\PDOException $e) {
        echo $e->getMessage();
        exit(1);
    }
    exit(0);    
}
