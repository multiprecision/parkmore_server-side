<?php

date_default_timezone_set('UTC');
session_start();

// indicate that we're sending json content
header("Content-Type: application/json");

// connect to database system
$mysql_connection = mysqli_connect( "localhost", "root", "", "parkmore", "3306");

// check if successful
if (mysqli_connect_errno())
{
    printf("Connect failed: %s\n", mysqli_connect_error());
}
// accept POST request
if(strcasecmp($_SERVER["REQUEST_METHOD"], "POST") != 0)
{
    return;
}

// accept json
$content_type = isset($_SERVER["CONTENT_TYPE"]) ? trim($_SERVER["CONTENT_TYPE"]) : "";

if(strcasecmp($content_type, "application/json") != 0)
{
    return;
}

// get raw input
$content = trim(file_get_contents("php://input"));

// parse it as json
$decoded = json_decode($content, true);

if(!is_array($decoded))
{
    $data = array("success" => false, "error_codes" => array("parsing_json_failed"));
    print(json_encode($data));
    return;
}

// must include action field
if (!array_key_exists("action", $decoded)) 
{
    return;
}

switch ($decoded["action"])
{
    case "login":
        if (!array_key_exists("email", $decoded) || !array_key_exists("email", $decoded))
        {
            $data = array("success" => false, "error_codes" => array("empty"));
            print(json_encode($data));
            return;
        }
        $email_input = trim(mysqli_real_escape_string($mysql_connection, $decoded["email"]));
        $password_input = trim(mysqli_real_escape_string($mysql_connection, $decoded["password"]));
        $hashedsaltedpassword = hash("sha512", $email_input . $password_input);
        $result = mysqli_query($mysql_connection, "SELECT id, email, password_hash FROM user WHERE email='$email_input'");
        // check for errors
        if ($result === false)
        {
            print(mysqli_error($mysql_connection));
            return;
        }
        $row =  mysqli_fetch_assoc($result);
        $count = mysqli_num_rows($result); // return value should be one if input was correct
        mysqli_free_result($result);
        if($count == 1 && $row["password_hash"] == $hashedsaltedpassword)
        {
            $_SESSION["user_id"] = $row["id"];
            $data = array("success" => true);
            print(json_encode($data));
            return;
        }
        else
        {
            $data = array("success" => false, "error_codes" => array("incorrect_login"));
            print(json_encode($data));
            return;
        }
        break;
        
    case "logout":
        if(!isset($_SESSION["user_id"]))
        {
            $data = array("success" => false, "error_codes" => array("not_logged_in"));
            print(json_encode($data));
            return;
        }
        
        unset($_SESSION["user_id"]);
        // unset all of the session variables.
        $_SESSION = array();
        // delete the session cookie
        if (ini_get("session.use_cookies"))
        {
            $params = session_get_cookie_params();
            setcookie(session_name(), "", time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
        }
        // destroy the session.
        session_destroy();
        
        $data = array("success" => true);
        print(json_encode($data));
        return;
        break;
        
    case "register":
        if(isset($_SESSION["user_id"]))
        {
            $data = array("success" => false, "error_codes" => array("already_logged_in"));
            print(json_encode($data));
            return;
        }
        $email_input = trim(mysqli_real_escape_string($mysql_connection, $decoded["email"]));
        $password_input = trim(mysqli_real_escape_string($mysql_connection, $decoded["password"]));
        $license_plate_number_input = trim(mysqli_real_escape_string($mysql_connection, $decoded["license_plate_number"]));
        $name_input = trim(mysqli_real_escape_string($mysql_connection, $decoded["name"]));

        if(!filter_var($email_input, FILTER_VALIDATE_EMAIL))
        {
            $data = array("success" => false, "error_codes" => array("invalid_email_address"));
            print(json_encode($data));
            return;
        }
        if(strlen($password_input) < 4 || strlen($password_input) > 255)
        {
            $data = array("success" => false, "error_codes" => array("invalid_password_length"));
            print(json_encode($data));
            return;
        }
        $hashedsaltedpassword = hash("sha512", $email_input . $password_input);

        $query = "SELECT email FROM user WHERE email = '$email_input'";
        $result = mysqli_query($mysql_connection, $query);

        if($result === false)
        {
            print(mysqli_error($mysql_connection));
            return;
        }
        
        $count = mysqli_num_rows($result);
        if($count == 0)
        {
            if(mysqli_query($mysql_connection, "INSERT INTO user(email, password_hash, license_plate_number, name, create_time) VALUES('$email_input', '$hashedsaltedpassword', '$license_plate_number_input', '$name_input', now());"))
            {
                $data = array("success" => true);
                print(json_encode($data));
                return;
            }
            else
            {
                print(mysqli_error($mysql_connection));
                return;
            }
        }
        else
        {
            $data = array("success" => false, "error_codes" => array("email_taken"));
            print(json_encode($data));
            return;
        }
        break;
        
    case "get_user_data":
        if(!isset($_SESSION["user_id"]))
        {
            $data = array("success" => false, "error_codes" => array("not_logged_in"));
            print(json_encode($data));
            return;
        }
        $user_id = $_SESSION["user_id"];
        $result = mysqli_query($mysql_connection, "SELECT name, license_plate_number FROM user WHERE id='$user_id'");
        // check for errors
        if ($result === false)
        {
            print(mysqli_error($mysql_connection));
            return;
        }
        $row =  mysqli_fetch_assoc($result);
        $count = mysqli_num_rows($result);
        mysqli_free_result($result);
        if($count == 1)
        {
            $name = $row["name"];
            $license_plate_number = $row["license_plate_number"];
            $data = array("success" => true, "name" => $name, "license_plate_number" => $license_plate_number);
            print(json_encode($data));
            return;
        }
        else
        {
            $data = array("success" => false, "error_codes" => array("not_found"));
            print(json_encode($data));
            return;
        }
        $data = array("success" => true, "error_codes" => array("not_logged_in"));
        print(json_encode($data));
        return;
        break;
        
    case "reserve":
        if(!isset($_SESSION["user_id"]))
        {
            $data = array("success" => false, "error_codes" => array("not_logged_in"));
            print(json_encode($data));
            return;
        }
        $user_id = $_SESSION["user_id"];
        $start_utc = trim(mysqli_real_escape_string($mysql_connection, $decoded["start_utc"]));
        $end_utc = trim(mysqli_real_escape_string($mysql_connection, $decoded["end_utc"]));
        $format = "Y-m-d H:i:s";
        $ds = DateTime::createFromFormat($format, $decoded["start_utc"]);
        if (!($ds && $ds->format($format) == $decoded["start_utc"]))
        {
            $data = array("success" => false, "error_codes" => array("invalid_date"));
            print(json_encode($data));
            return;
        }
        else
        {
            // accept 10 minutes interval
            $test = $ds->format("i:s");
            if (!(strcmp($test, "00:00") == 0 || strcmp($test, "10:00") == 0 || strcmp($test, "20:00") == 0 || strcmp($test, "30:00") == 0 || strcmp($test, "40:00") == 0 || strcmp($test, "50:00") == 0))
            {
                $data = array("success" => false, "error_codes" => array("invalid_date_2_start"));
                print(json_encode($data));
                return;
            }
        }
        $de = DateTime::createFromFormat($format, $decoded["end_utc"]);
        if (!($de && $de->format($format) == $decoded["end_utc"]))
        {
            $data = array("success" => false, "error_codes" => array("invalid_date"));
            print(json_encode($data));
            return;
        }
        else
        {
            // accept 10 minutes interval
            $test = $de->format("i:s");
            if (!(strcmp($test, "00:00") == 0 || strcmp($test, "10:00") == 0 || strcmp($test, "20:00") == 0 || strcmp($test, "30:00") == 0 || strcmp($test, "40:00") == 0 || strcmp($test, "50:00") == 0))
            {
                $data = array("success" => false, "error_codes" => array("invalid_date_2_end"));
                print(json_encode($data));
                return;
            }
        }
        if ($de <= $ds)
        {
        	$a =  $de->format($format);
        	$b =  $ds->format($format);
            $data = array("success" => false, "error_codes" => array("end_time_is_less_than_or_equal_start_time"));
            print(json_encode($data));
            return;
        }
        $query = "SELECT * FROM reservation WHERE start_utc <= '$end_utc' and end_utc >= '$end_utc';";
        $result = mysqli_query($mysql_connection, $query);
        // check for errors
        if ($result === false)
        {
            print(mysqli_error($mysql_connection));
            return;
        }
        $count = mysqli_num_rows($result); // return value should be one if input was correct
        if ($count > 0)
        {
            $data = array("success" => false, "error_codes" => array("parking_spot_unavailable"));
            print(json_encode($data));
            return;
        }
        $query = "INSERT INTO reservation(user_id, start_utc, end_utc, timestamp) VALUES('$user_id', '$start_utc', '$end_utc', now());";
        if(mysqli_query($mysql_connection, $query))
        {
            $data = array("success" => true);
            print(json_encode($data));
            return;
        }
        else
        {
            $data = array("success" => false, "error_codes" => array("mysql_error"));
            print(mysqli_error($mysql_connection));
            print(json_encode($data));
            return;
        }
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
