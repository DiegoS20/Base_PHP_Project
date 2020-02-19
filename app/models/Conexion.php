<?php
class Conexion
{
    function __construct() {
        if(PHP_SESSION_ACTIVE != session_status())
            session_start();
    }

   /**
    * 
    * It will create a PDO instance with the connection to the database
    *
    *
    * @return bool false will be returned if something went wrong when trying to connect with the database
    * @return PDO If everything it's ok, this will return an instance of PDO with the connection to the database
    */
    private function connect_to_db(string $host, string $database, string $user, string $password, array $options) {
        try {
            $instance = new PDO(
                "mysql:host=$host;dbname=$database",
                $user,
                $password,
                $options
            );
        } catch (PDOException $e) {
            return false;
        }
        
        return $instance;
    }

    /**
     * It makes a query
     * 
     * @return false This function will return false if the query fail
     * @return array An array will be returned with the elements of the query
     * 
     */
    public function makeQuery(string $sql, array $arrayParams = null, bool $row_count = false) {
        // Connecting to database
        $this->conexion = $this->connect_to_db(
            Conexion::DB_SERVER, Conexion::DB_BBDD,
            Conexion::DB_USER,
            Conexion::DB_PASSWORD,
            Conexion::arrayOptions
        );

        if (!$this->conexion) {
            return false;
        }

        // Creating an PDOStatement for preparing query
        $stmt = $this->conexion->prepare($sql);
        // Executing the preparing query
        $stmt->execute($arrayParams);
        if('00000' !== $stmt->errorCode()) 
            return false;

        if (!$row_count) $result = $stmt->fetchAll();
        else $affected_rows = $stmt->rowCount();

        // Cleaning and closing conexion
        $stmt->closeCursor();
        $this->conexion = null;
        $stmt = null;

        return $row_count ? $affected_rows : $result;
    }

    /**
     * This function inserts an element in the database based on the @param $elements 
     * 
     * @param array $elements It's an array with the elements to insert into the database. These elements has to be in order with the columns in the database
     * @param bool $id If this param is in true (by default), the function will suppose that the table on the database begins with an id column
     * 
     * @return true If the record was inserted successfuly
     * @return false When the record could not be inserted
     */
    public function insert(string $table, array $elements, bool $id = true) {
        // Starting the sql sentence
        $sql = "INSERT INTO $table VALUES(";
        
        // If the table has an id as it first field (99% of the cases) it will begin with an extra '?'
        if ($id) $sql .= "?, ";
        
        // Knowing the keys of the $elements depending on the version of PHP that is being used
        $phpversion = explode(".", phpversion())[1];
        if ($phpversion >= 3) $last_key = array_key_last($elements);
        else {
            $keys = array_keys($elements);
            $last_key = end($keys);
        }

        // Completing the sql sentence depending on the number of data we want to insert in the table
        foreach ($elements as $key => $e) {
            if($key == $last_key) {
                $sql .= "?)";
                break;
            }
            $sql .= "?, ";
        }

        // If the table has an id as it first field (99% of the cases), we add a null value as the first value of the prepared sentence params
        if ($id) array_unshift($elements, null);

        // Executing the prepared query
        try {
            $success = $this->makeQuery($sql, $elements, true);
        } catch (PDOException $e) {
            return false;
        }
        
        return boolval($success);
    }

    /**
     * It returns all the elements of a table in the database
     * 
     * @return array with the data from the database
     * @return false if the query could not be possible
     * 
     */
    public function select_all(string $table) {
        // Bringing all the data of the table
        try { return $this->makeQuery("SELECT * FROM $table"); } 
        catch (PDOException $e) { return false; }
    }

    /**
     * It returns the elements of a table in the database that match with the parameters
     * 
     * @param array $parameters A key => value array where the 'key' is the column of the table and 'value' is the value of the condition
     * 
     * @return array with the data from the database
     * @return false if the query could not be possible
     * 
     */
    public function select(string $table, array $parameters) {
        // Starting the sql sentence
        $sql = "SELECT * FROM $table WHERE ";
        
        // Knowing the keys of the parameters of the search
        $keys = array_keys($parameters);
        
        $arrayParams = []; // params of the prepared query will be placed here

        // Completing the sql sentence depending on the number of data we want to insert in the table
        foreach($parameters as $key => $value) {
            $sql .= "$key = :$key";
            $arrayParams[":$key"] = $value;
            if($key != end($keys)) $sql .= " AND ";
        }

        // Executing the sql query
        try {
            return $this->makeQuery($sql, $arrayParams);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * It updates the information of the database
     * 
     * @param  array $values_to_change It's a key value array that contains the columns that will be changed on the table. The 'key' is the name of the column on the table and the 'value'is the new value of that element.
     * @param array $condition It's a key value array that will be used on the WHERE clause of the sql sentence. The 'key' is the name of the column on the table and the 'value' is the value of the condition.
     * 
     * @return true If something was changed
     * @return false If nothing was changed
     * 
     * @throws Exception If the $values_to_change or the $condition are not associative arrays
     */
    public function update(string $table, array $values_to_change, array $condition) {
        // Validating the dates (arrays in specific)
        if(!$this->is_assoc($condition) || !$this->is_assoc($values_to_change))
            throw new Exception("One the of the required arrays are not associative", 1);

        // Starting the sql sentence
        $sql = "UPDATE $table SET ";

        // Knowing the keys of the values to change
        $keys = array_keys($values_to_change);

        $array_params = []; // params of the prepared query will be placed here

        // Completing the sql sentence depending on the number of values changed
        foreach ($values_to_change as $key => $value) {
            $sql .= "$key=:$key";
            $array_params[":$key"] = $value;
            if($key != end($keys)) $sql .= ", ";
        }

        // Preparing the end of the query
        $sql .= " WHERE ";

        // Knowing the keys for the condition part of the query
        $condition_keys = array_keys($condition);

        // Completing the sql sentence depending on the number of values changed
        foreach ($condition as $key => $value) {
            $sql .= "$key = :$key" . "con";
            $array_params[":$key" . "con"] = $value;
            if($key != end($condition_keys)) $sql .= " AND ";
        }

        // Executing the query
        $success = $this->makeQuery($sql, $array_params, true);

        return $success > 0;
    }

    /**
     * It deletes an element or elements from a table where the $index_name and the $index_value match.
     * 
     * @return true if something was deleted
     * @return false if nothing was deleted
     * 
     */
    public function delete(string $table, string $index_name, string $index_value) {
        // Creating the sql sentence
        $sql = "DELETE FROM $table WHERE $index_name = $index_value";

        // Executing the sql sentence
        try { $success = $this->makeQuery($sql, null, true); }
        catch (PDOException $e) { return false; }

        return $success > 0;
    }

    /**
     * Get the path from the full proyect begin
     * 
     * @return string The path
     */
    public function get_base_url() {
        // Getting the full path from the root of the server to the begining of the storage of the website
        $from_0 = explode('/', $_SERVER['DOCUMENT_ROOT']);

        // Getting the $from_0 AND the path of the internal estructure of the website
        $origin = explode("\\", __DIR__);

        // Creating the path from the root of the server until the begining of the website
        $base_url = implode("/", $from_0) . "/" . $origin[count($from_0)] . "/";
        return $base_url;
    }

    /**
     * Join the given path to the base url
     * 
     * @return string The full path joined
     */
    public function get_path_from_origin(string $destiny_from_origin) {
        return $this->get_base_url() . $destiny_from_origin;
    }

    /**
     * Prepare the data to avoid SQL Inyections
     * 
     * @param string $variable The data to prepare
     * @param string $ent_quotes The flags of the htmlentities function (https://www.php.net/manual/es/function.htmlentities.php)
     * 
     * @return string The data prepared
     */
    public function prepareVariableToPreparedQuery (string $variable, bool $etn_quotes = false) : string {
        if ($etn_quotes);
            return htmlentities(addslashes($variable), ENT_QUOTES);

        return htmlentities(addslashes($variable));
    }

    /**
     * Init a session for an user system
     * 
     * @param array $session_vars The data that will be saved in the session storage
     * @param bool $set_remember_cookies Create cookies to remember the user logged in
     * 
     * @return void
     */
    public function initSession(array $session_vars, bool $set_remember_cookies = false) {
        $_SESSION['remember'] = true;
        foreach ($session_vars as $var => $value)
            $_SESSION[$var] = $value;

        if($set_remember_cookies) {
            $this->setSessionCookie($session_vars['token_session']);
        }
    }

    /**
     * Will create two cookies: remember and token_session
     * 
     * @param string $token_session It's the token of the user. This token will be converted into a cookie to be used in the future
     */
    public function setSessionCookie(string $token_session) {
        setcookie("remember", true, time() + 604800, "/", "", false, true);
        setcookie("token_session", $token_session, time() + 604800, "/", "", false, true);
    }
    
    /**
     * Prepare an image creating a random uniq name and joinit with the directory where it'll be saved
     * 
     * @param $file_input The image from the form
     * @param string $directory the folder inside images/ where the image will be saved
     * 
     * @return array with an "tmp_name" and "destiny" values in it.
     */
    public function prepareImage($file_input, string $directory) {
        $img = $file_input;
        $extension_imagen = explode(".", $img['name']);
        $new_name_image = random_int(100000, 999999) . uniqid() . "." . end($extension_imagen);

        return [
            "tmp_name" => $file_input['tmp_name'],
            "destiny" => $directory . $new_name_image,
        ];
    }

    /**
     * Verify if a session has been initiated
     * 
     * @param string $to Where will be redirected if is not logged in
     * 
     * @return void
     */
    public function is_staff_logged_in(string $to) {
        if(session_status() == PHP_SESSION_NONE) session_start();
        if(isset($_COOKIE['remember'])) {
            $login = $this->select("user", [ "token_session" => $_COOKIE['token_session'] ]);
            unset($login[0]['name']);
            unset($login[0]['email']);
            unset($login[0]['password']);
            $this->initSession($login[0], true);
        }
        if(isset($_SESSION['remember']) && $_SESSION['remember']) return;
        header("Location:".$this->get_path_from_origin($to));
        exit();
    }

    /**
     * Protocol to follow if an error occurs
     * 
     * @param string $code the code of the error
     * 
     * @return void
     */
    public function error_protocol(string $message, string $code) {
        if(session_status() == PHP_SESSION_NONE)
            session_start();

        $_SESSION['error_info'] = [
            "code" => $code,
            "message" => $message
        ];
    }

    /**
     * Protocol to follow if a proccess is success
     * 
     * @return void
     */
    public function success_protocol(string $message) {
        if(session_status() == PHP_SESSION_NONE)
            session_start();

        $_SESSION['success_message'] = $message;
    }

    /**
     * Verify wich values has been changed with refence given
     * 
     * @return array The values changed
     * @return false If there's not any value changed
     */
    public function values_changed(array $reference, array $actual_values) {
        foreach ($reference as $key => $value) {
            if(array_key_exists($key, $actual_values) && $value != $actual_values[$key])
                $values_changed[$key] = $actual_values[$key];
        }

        return $values_changed ?? false;
    }

    // -------------------------------- Private functions --------------------------------

    /**
     * Verify if an array is associative
     * 
     * @return bool
     */
    private function is_assoc(array $array) {
        return array_keys($array) !== range(0, count($array) - 1);
    }
    
    // Datos de conexion para servidor local
    private const DB_SERVER = "localhost"; // Name of server
    private const DB_BBDD = ""; // Name of data base
    private const DB_USER = "root"; // Name of the user of the data_base
    private const DB_PASSWORD = ""; // Password to log in to the data base
    private const arrayOptions = array(
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_EMULATE_PREPARES => FALSE,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'",
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ); // Some array options
}
$conexion = new Conexion(); // An instance to use wherever we need it
