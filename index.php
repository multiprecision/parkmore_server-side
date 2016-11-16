<?php

session_start();

$mysql_connection = mysqli_connect( "localhost", "root", "", "parkmore", "3306");

if (mysqli_connect_errno())
{
    printf("Connect failed: %s\n", mysqli_connect_error());
}

$content = file_get_contents("php://input");

if(strcasecmp($_SERVER["REQUEST_METHOD"], "POST") != 0)
{
    return;
}
 
$content_type = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : "";

if(strcasecmp($content_type, "application/json") != 0)
{
    return;
}
 
$content = trim(file_get_contents("php://input"));
 
$decoded = json_decode($content, true);

if(!is_array($decoded))
{
    $data = array("success" => false, "error_codes" => array("decoding_json_failed"));
    echo json_encode($data);
    return;
}

header("Content-Type: application/json");

switch ($decoded["action"])
{
    case "login":
        $email_input = trim(mysqli_real_escape_string($mysql_connection, $decoded["email"]));
        $password_input = trim(mysqli_real_escape_string($mysql_connection, $decoded["password"]));
        $hashedsaltedpassword = hash("sha512", $email_input . $password_input);
        $result = mysqli_query($mysql_connection, "SELECT id, email, password_hash FROM user WHERE email='$email_input'");
        if ($result === false)
        {
            echo(mysqli_error($mysql_connection));
            return;
        }
        $row =  mysqli_fetch_assoc($result);
        $count = mysqli_num_rows($result); // return value should be one if input was correct
        mysqli_free_result($result);
        if($count == 1 && $row["password_hash"] == $hashedsaltedpassword)
        {
            $_SESSION["user"] = $row["id"];
            $data = array("success" => true);
            echo json_encode($data);
        }
        else
        {
            $data = array("success" => false, "error_codes" => array("wrong_login_info"));
            echo json_encode($data);
        }
        break;
    case "logout":
        if(!isset($_SESSION["user"]))
        {
            $data = array("success" => false, "error_codes" => array("not_loggedin"));
            echo json_encode($data);
            return;
        }
        unset($_SESSION["user"]);
        session_destroy();
        $data = array("success" => true);
        echo json_encode($data);
        return;
        break;
    case "register":
        if(isset($_SESSION["user"])!="")
        {
            throw new Exception("Already logged in.");
        }
        $email_input = trim(mysqli_real_escape_string($mysql_connection, $decoded["email"]));
        $password_input = trim(mysqli_real_escape_string($mysql_connection, $decoded["password"]));
        $license_plate_number_input = trim(mysqli_real_escape_string($mysql_connection, $decoded["license_plate_number"]));
        $name_input = trim(mysqli_real_escape_string($mysql_connection, $decoded["name"]));

        if(!filter_var($email_input, FILTER_VALIDATE_EMAIL) || strlen($password_input) < 4 || strlen($password_input) > 255)
        {
            $data = array("success" => false, "error_codes" => array("invalid_data"));
            echo json_encode($data);
            return;
        }
        $hashedsaltedpassword = hash("sha512", $email_input . $password_input);

        $query = "SELECT email FROM user WHERE email = '$email_input'";
        $result = mysqli_query($mysql_connection, $query);

        if($result === false)
        {
            echo(mysqli_error($mysql_connection));
            return;
        }
        
        $count = mysqli_num_rows($result);
        if($count == 0)
        {
            if(mysqli_query($mysql_connection, "INSERT INTO user(email, password_hash, license_plate_number, name, create_time) VALUES('$email_input', '$hashedsaltedpassword', '$license_plate_number_input', '$name_input', now());"))
            {
                $data = array("success" => true);
                echo json_encode($data);
            }
            else
            {
                echo(mysqli_error($mysql_connection));
            }
        }
        else
        {
            $data = array("success" => false, "error_codes" => array("email_taken"));
            echo json_encode($data);
        }
        break;
    // TODO
    case "reserve":
        break;
    case "extend":
        break;
    case "cancel":
        break;
    default:
        break;
}

mysqli_close ($mysql_connection);
?>
