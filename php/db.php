<?php

class Db {
    private static $uniqueInstance;

    var $show_errors = false;
    var $suppress_errors = false;
    var $last_error = '';
    var $num_queries = 0;
    var $num_rows = 0;
    var $rows_affected = 0;
    var $insert_id = 0;
    var $last_query;
    var $last_result;
    var $col_info;
    var $queries;
    var $prefix = '';
    var $ready = false;
    var $charset;
    var $collate;
    var $real_escape = false;
    var $dbuser;
    var $func_call;

    function __construct() {

        if ( DEVELOPMENT )
            $this->show_errors();

        $this->dbuser = DB_USER;
        $this->dbpassword = DB_PASSWORD;
        $this->dbname = DB_NAME;
        $this->dbhost = DB_HOST;

        if(USE_PDO){
            $this->ezSQL = new ezSQL_pdo('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME, DB_USER, DB_PASSWORD);

            $commands = array();
            $commands[] = 'SET SQL_MODE=ANSI_QUOTES';
            $commands[] = "SET NAMES '" . DB_CHARSET . "'";

            foreach ($commands as $value)
            {
                $this->ezSQL->dbh->exec($value);
            }
        }else{
            $this->ezSQL = new ezSQL_mysql(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);
            mysql_query("SET NAMES '" . DB_CHARSET . "'");
        }


    }

    function __destruct() {
        return true;
    }

    public static function GetInstance(){
        if(self::$uniqueInstance == null){
            self::$uniqueInstance = new Db();
        }
        return self::$uniqueInstance;
    }


    function _weak_escape( $string ) {
        return  $string;
    }

    function _real_escape( $string ) {
        return $this->ezSQL->escape($string);
    }

    function _escape( $data ) {
        if ( is_array( $data ) ) {
            foreach ( (array) $data as $k => $v ) {
                if ( is_array($v) )
                    $data[$k] = $this->_escape( $v );
                else
                    $data[$k] = $this->_real_escape( $v );
            }
        } else {
            $data = $this->_real_escape( $data );
        }

        return $data;
    }

    function escape( $data ) {
        if ( is_array( $data ) ) {
            foreach ( (array) $data as $k => $v ) {
                if ( is_array( $v ) )
                    $data[$k] = $this->escape( $v );
                else
                    $data[$k] = $this->_weak_escape( $v );
            }
        } else {
            $data = $this->_weak_escape( $data );
        }

        return $data;
    }

    function escape_by_ref( &$string ) {
        $string = $this->_real_escape( $string );
    }

    function prepare( $query = null ) { // ( $query, *$args )
        if ( is_null( $query ) )
            return;

        $args = func_get_args();
        array_shift( $args );
        // If args were passed as an array (as in vsprintf), move them up
        if ( isset( $args[0] ) && is_array($args[0]) )
            $args = $args[0];
        $query = str_replace( "'%s'", '%s', $query ); // in case someone mistakenly already singlequoted it
        $query = str_replace( '"%s"', '%s', $query ); // doublequote unquoting
        $query = preg_replace( '|(?<!%)%s|', "'%s'", $query ); // quote the strings, avoiding escaped strings like %%s
        array_walk( $args, array( &$this, 'escape_by_ref' ) );
        return @vsprintf( $query, $args );
    }

    function print_error( $str = '' ) {

        if ( !$str )
            $str = mysql_error( $this->dbh );

        if ( $this->suppress_errors )
            return false;

        // Are we showing errors?
        if ( ! $this->show_errors )
            return false;

        $str   = htmlspecialchars( $str, ENT_QUOTES );
        $query = htmlspecialchars( $this->last_query, ENT_QUOTES );

        if(!$str){
            print "<div id='error'>
			<p><strong>Database error:</strong> [$str]<br />
			<code>$query</code></p>
			</div>";
        }else{
            if ( $EZSQL_ERROR ){
                $this->ezSQL->vardump($EZSQL_ERROR);
            }
        }


    }

    function show_errors( $show = true ) {
        $errors = $this->show_errors;
        $this->show_errors = $show;
        if(isset($this->ezSQL)){
            $this->ezSQL->show_errors();
        }
        return $errors;
    }

    function hide_errors() {
        $show = $this->show_errors;
        $this->show_errors = false;
        if(isset($this->ezSQL)){
            $this->ezSQL->hide_errors();
        }
        return $show;
    }

    function suppress_errors( $suppress = true ) {
        $errors = $this->suppress_errors;
        $this->suppress_errors = (bool) $suppress;
        return $errors;
    }

    function flush() {
        $this->last_result = array();
        $this->col_info    = null;
        $this->last_query  = null;
    }


    function query( $query ) {
        //if ( ! $this->ready )
        //return false;


        $return_val = 0;
        $this->flush();

        // Log how the function was called
        $this->func_call = "\$db->query(\"$query\")";

        // Keep track of the last query for debug..
        $this->last_query = $query;

        if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES )
            $this->timer_start();

        $this->result = $this->ezSQL->query($query);
        $this->num_queries++;

        if ( defined( 'SAVEQUERIES' ) && SAVEQUERIES )
            $this->queries[] = array( $query, $this->timer_stop() );

        /* If there is an error then take note of it..
        if ( $this->last_error = mysql_error( $this->dbh ) ) {
            $this->print_error();
            return false;
        }
        */

        if ( preg_match( '/^\s*(create|alter|truncate|drop) /i', $query ) ) {
            $return_val = $this->result;
        } elseif ( preg_match( '/^\s*(insert|delete|update|replace) /i', $query ) ) {
            $this->rows_affected = $this->ezSQL->rows_affected;
            // Take note of the insert_id
            if ( preg_match( '/^\s*(insert|replace) /i', $query ) ) {
                $this->insert_id = $this->ezSQL->insert_id;
            }
            // Return number of rows affected
            $return_val = $this->rows_affected;
        } else {
            $return_val     = $this->rows_affected;
        }

        return $return_val;
    }

    function insert( $table, $data, $format = null ) {
        return $this->_insert_replace_helper( $table, $data, $format, 'INSERT' );
    }

    function replace( $table, $data, $format = null ) {
        return $this->_insert_replace_helper( $table, $data, $format, 'REPLACE' );
    }

    function _insert_replace_helper( $table, $data, $format = null, $type = 'INSERT' ) {
        if ( ! in_array( strtoupper( $type ), array( 'REPLACE', 'INSERT' ) ) )
            return false;
        $formats = $format = (array) $format;
        $fields = array_keys( $data );
        $formatted_fields = array();
        foreach ( $fields as $field ) {
            if ( !empty( $format ) )
                $form = ( $form = array_shift( $formats ) ) ? $form : $format[0];
            else
                $form = '%s';
            $formatted_fields[] = $form;
        }
        $sql = "{$type} INTO `$table` (`" . implode( '`,`', $fields ) . "`) VALUES ('" . implode( "','", $formatted_fields ) . "')";
        return $this->query( $this->prepare( $sql, $data ) );
    }

    function update( $table, $data, $where, $format = null, $where_format = null ) {
        if ( ! is_array( $data ) || ! is_array( $where ) )
            return false;

        $formats = $format = (array) $format;
        $bits = $wheres = array();
        foreach ( (array) array_keys( $data ) as $field ) {
            if ( !empty( $format ) )
                $form = ( $form = array_shift( $formats ) ) ? $form : $format[0];
            else
                $form = '%s';
            $bits[] = "`$field` = {$form}";
        }

        $where_formats = $where_format = (array) $where_format;
        foreach ( (array) array_keys( $where ) as $field ) {
            if ( !empty( $where_format ) )
                $form = ( $form = array_shift( $where_formats ) ) ? $form : $where_format[0];
            else
                $form = '%s';
            $wheres[] = "`$field` = {$form}";
        }

        $sql = "UPDATE `$table` SET " . implode( ', ', $bits ) . ' WHERE ' . implode( ' AND ', $wheres );
        return $this->query( $this->prepare( $sql, array_merge( array_values( $data ), array_values( $where ) ) ) );
    }

    function get_var( $query = null) {
        return $this->ezSQL->get_var( $query);
    }

    function get_row( $query = null) {
        return $this->ezSQL->get_row( $query, OBJECT);
    }

    function get_col( $query = null) {
        return $this->ezSQL->get_col( $query);
    }

    function get_results( $query = null) {
        return $this->ezSQL->get_results( $query);
    }

    function get_col_info( $info_type = 'name', $col_offset = -1 ) {
        if ( $this->col_info ) {
            if ( $col_offset == -1 ) {
                $i = 0;
                $new_array = array();
                foreach( (array) $this->col_info as $col ) {
                    $new_array[$i] = $col->{$info_type};
                    $i++;
                }
                return $new_array;
            } else {
                return $this->col_info[$col_offset]->{$info_type};
            }
        }
    }

    function timer_start() {
        $mtime            = explode( ' ', microtime() );
        $this->time_start = $mtime[1] + $mtime[0];
        return true;
    }

    function timer_stop() {
        $mtime      = explode( ' ', microtime() );
        $time_end   = $mtime[1] + $mtime[0];
        $time_total = $time_end - $this->time_start;
        return $time_total;
    }

    function bail( $message) {
        if ( !$this->show_errors ) {
            $this->error = $message;
            return false;
        }
        die($message);
    }
}