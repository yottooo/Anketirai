<?php
/**
 * Input class - Manages all types of incoming data, including Server, Env and User-input information
 *
 * @package  Audith CMS codename Persephone
 * @author   Shahriyar Imanov <shehi@imanov.name>
 * @version  1.0
 */
class Input
{
        /**
         * Registry reference
         * @var Registry
         */
        private $Registry;

        /**
         * Safe version of $_COOKIE
         * @var array
         */
        public $cookie = array();

        /**
         * Internal cookie set for faster retrieval
         * @var array
         */
        private $_cookie_set = array();

        /**
         * Object used for character encoding conversions
         * @var object
         */
        public $encoding_converter;

        /**
         * Safe version of $_ENV
         * @var array
         */
        public $env = array();

        /**
         * Safe version of $_GET
         * @var array
         */
        public $get = array();

        /**
         * HTTP Headers
         * @var array
         */
        public $headers = array( 'request' => array( '_is_ajax' => false ) );
        /**
         * Whether or not, Input::clean_makesafe_recursively() was applied to the selected Superglobal.
         * @var array
         */
        private $_is_cleanup_done_for = array(
                        'post'     => false,
                        'get'      => false,
                        'request'  => false,
                        'cookie'   => false,
                        'server'   => false,
                        'env'      => false
                );

        /**
         * Safe version of $_POST
         * @var array
         */
        public $post = array();

        /**
         * QUERY_STRING (formatted)
         * @var string
         */
        public $query_string_formatted = "";

        /**
         * QUERY_STRING (real)
         * @var string
         */
        public $query_string_real = "";

        /**
         * QUERY_STRING (safe)
         * @var string
         */
        public $query_string_safe = "";

        /**
         * Safe version of $_REQUEST
         * @var array
         */
        public $request = array();

        /**
         * Safe version of $_SERVER
         * @var array
         */
        public $server = array();

        /**
         * Session cookies of high priority, sensitivity and importance - Only managed through $_SESSION superglobal
         * @var array
         */
        public $sensitive_cookies = array();


        /**
         * Constructor
         *
         * @param array  Registry object reference
         */
        public function __construct ( Registry $Registry )
        {
                //----------
                // Prelim
                //----------

                $this->Registry = $Registry;
        }


        /**
         * Method overloading for Input class
         *
         * @param     string      The name of the method being called.
         * @param     array       Enumerated array containing the parameters passed to the $name'd method.
         * @throws    Exception   In case, if method is not one of the pre-defined Six.
         */
        public function __call ( $name , $arguments )
        {
                if ( !in_array( $name, array( "post", "get", "cookie", "request", "server", "env" ) ) )
                {
                        throw new Exception( "Input::" . $name . "() not declared!" );
                        exit;
                }

                $_name_of_superglobal = "_" . strtoupper( $name );
                $_max_iteration_level_for_cleanup = in_array( $name, array( "server", "env" ) ) ? 1 : 10;

                # $arguments[0] is the index of the value, to be fetched from within the array.
                if ( !empty( $arguments[0] ) and array_key_exists( $arguments[0], $this->$name ) )
                {
                        return $this->$name[ $arguments[0] ];
                }
                elseif ( !empty( $arguments[0] ) and array_key_exists( $arguments[0], $GLOBALS[ $_name_of_superglobal ] ) )
                {
                        return $this->$name[ $this->clean__makesafe_key( $arguments[0] ) ] = $this->clean__makesafe_value( $GLOBALS[ $_name_of_superglobal ][ $arguments[0] ], array(), true );
                }
                elseif ( !empty( $arguments[0] ) and !array_key_exists( $arguments[0], $GLOBALS[ $_name_of_superglobal ] ) )
                {
                        return null;
                }
                else
                {
                        if ( $this->_is_cleanup_done_for[ $name ] === true )
                        {
                                return $this->$name;
                        }
                        $this->_is_cleanup_done_for[ $name ] = true;
                        return $this->$name = $this->clean__makesafe_recursively( $GLOBALS[ $_name_of_superglobal ], $_max_iteration_level_for_cleanup );
                }
        }


        /**
         * Destructor
         */
        public function _my_destruct ()
        {
                $this->Registry->logger__do_log( __CLASS__ . "::__destruct: Destroying class" );
        }


        /**
         * Inits INPUT environment
         *
         * @return void
         */
        public function init ()
        {
                //--------------------
                // Request headers
                //--------------------

                foreach ( $_SERVER as $_k=>$_v )
                {
                        if ( strpos( $_k, 'HTTP_') === 0 )
                        {
                                $_k = str_replace( array( "HTTP_" , "_" ), array( "", "-"), $_k );
                                $this->headers['request'][ strtoupper( $_k ) ] = $_v;
                        }
                }

                # Is it an XMLHttpRequest?
                if ( isset( $this->headers['request']['X-REQUESTED-WITH'] ) and $this->headers['request']['X-REQUESTED-WITH'] == 'XMLHttpRequest' )
                {
                        $this->headers['request']['_is_ajax'] = true;
                }

                //---------------------------------------------------
                // Strip slashes added by MAGIC_QUOTES_GPC mode
                //---------------------------------------------------

                if ( MAGIC_QUOTES_GPC_ON )
                {
                        $_POST   = $this->clean__stripslashes( $_POST );
                        $_GET    = $this->clean__stripslashes( $_GET );
                        $_COOKIE = $this->clean__stripslashes( $_COOKIE );
                }

                //-----------------------------------------
                // Perform basic cleanup on globals
                //-----------------------------------------

                $this->clean__globals( $_POST    );
                $this->clean__globals( $_GET     );
                $this->clean__globals( $_COOKIE  );
                $this->clean__globals( $_REQUEST );

                //----------------------------------
                // SERVER['PATH_INFO'] "exploded"
                //----------------------------------

                $this->Registry->config['page']['request'] = $this->my_parse_url();

                # Request path
                $this->Registry->logger__do_log( "Input::init() : Request-path = '" . $this->Registry->config['page']['request']['path'] . "'" , "INFO" );

                //-----------------------------
                // Make a safe QUERY STRING
                //-----------------------------

                $this->query_string_safe = $this->clean__excessive_separators(
                                $this->clean__makesafe_value( $this->my_getenv( "QUERY_STRING" ), array( "urldecode" ), true ),
                                "&amp;"
                        );
                $this->query_string_real = str_replace( '&amp;' , '&' , $this->query_string_safe );

                //----------------
                // Format it...
                //----------------

                $this->query_string_formatted = preg_replace( "#s=([a-z0-9]){32}#", '', $this->query_string_safe );
        }


        /**
         * Resource-efficient array_unshift() function
         */
        public function array_unshift_ref ( & $array, &$value )
        {
                $return   =  array_unshift( $array, "" );
                $array[0] =& $value;

                return $return;
        }


        /**
         * Check email address to see if it seems valid
         *
         * @param    string     Email address
         * @return   boolean
         */
        public function check_email_address ( $email = "" )
        {
                $email = trim( $email );

                return filter_var( $email, FILTER_VALIDATE_EMAIL ) === false ? false : true;
        }


        /**
         * Checks enclosing parentheses, matching opening and closing ones
         *
         * @param    string    String to check
         * @return   boolean   TRUE if successful, FALSE otherwise
         */
        public function check_enclosing_parentheses ( $t )
        {
                $t = "(" . $t . ")";
                preg_match_all(
                        '/
                        \(
                                (?:
                                        (?:
                                                (?>
                                                        [^()]+
                                                )
                                                |
                                                (?R)
                                        )*
                                )
                        \)
                        /xi' , $t , $_parentheses_check_matches );

                if ( $t != @$_parentheses_check_matches[0][0] )
                {
                        return false;
                }

                return true;
        }


        /**
         * Removes control characters (hidden spaces)
         *
         * @param    string        Input string
         * @return   integer   Parsed String
         *
         * @author              $Author: matt $
         * @copyright   (c) 2001 - 2009 Invision Power Services, Inc.
         * @license             http://www.invisionpower.com/community/board/license.html
         * @package             Invision Power Board
         */
        public function clean__control_characters ( $t )
        {
                if ( isset( $this->Registry->config['security']['strip_space_chr'] ) and $this->Registry->config['security']['strip_space_chr'] )
                {
                /**
                 * @see http://en.wikipedia.org/wiki/Space_(punctuation)
                 * @see http://www.ascii.cl/htmlcodes.htm
                 */
                        $t = str_replace( chr(160), ' ', $t );
                        $t = str_replace( chr(173), ' ', $t );

                        //$t = str_replace( chr(240), ' ', $t );         // Latin small letter eth

                //$t = str_replace( chr(0xA0), "", $t );         // Remove sneaky spaces        Same as chr 160
                //$t = str_replace( chr(0x2004), "", $t );       // Remove sneaky spaces
                //$t = str_replace( chr(0x2005), "", $t );       // Remove sneaky spaces
                //$t = str_replace( chr(0x2006), "", $t );       // Remove sneaky spaces
                //$t = str_replace( chr(0x2009), "", $t );       // Remove sneaky spaces
                //$t = str_replace( chr(0x200A), "", $t );       // Remove sneaky spaces
                //$t = str_replace( chr(0x200B), "", $t );       // Remove sneaky spaces
                //$t = str_replace( chr(0x200C), "", $t );       // Remove sneaky spaces
                //$t = str_replace( chr(0x200D), " ", $t );      // Remove sneaky spaces
                //$t = str_replace( chr(0x202F), " ", $t );      // Remove sneaky spaces
                //$t = str_replace( chr(0x205F), " ", $t );      // Remove sneaky spaces
                //$t = str_replace( chr(0x2060), "", $t );       // Remove sneaky spaces
                //$t = str_replace( chr(0xFEFF), "", $t );       // Remove sneaky spaces
                }

                return $t;
        }


        /**
         * Clean possible javascipt codes
         *
         * @param   String  Input
         * @return  String  Cleaned Input
         */
        /*
        public function clean__evil_tags ( $t )
        {
                $t = preg_replace( "/javascript/i" , "j&#097;v&#097;script", $t );
                $t = preg_replace( "/alert/i"      , "&#097;lert"          , $t );
                $t = preg_replace( "/about:/i"     , "&#097;bout:"         , $t );
                $t = preg_replace( "/onmouseover/i", "&#111;nmouseover"    , $t );
                $t = preg_replace( "/onclick/i"    , "&#111;nclick"        , $t );
                $t = preg_replace( "/onload/i"     , "&#111;nload"         , $t );
                $t = preg_replace( "/onsubmit/i"   , "&#111;nsubmit"       , $t );
                $t = preg_replace( "/<body/i"      , "&lt;body"            , $t );
                $t = preg_replace( "/<html/i"      , "&lt;html"            , $t );
                $t = preg_replace( "/document\./i" , "&#100;ocument."      , $t );

                return $t;
        }
        */


        /**
         * Cleans excessive leading and trailing + duplicate separator chars from delim-separated-values (such as CSV)
         *
         * @param    mixed    Data to clean
         * @param    string   RegEx-compatible separator-character (e.g., comma (,) in CSV)
         * @return   mixed    Output
         */
        public function clean__excessive_separators ( $i, $sep = "," )
        {
                if ( is_array( $i ) )
                {
                        foreach ( $i as $_k=>$_v )
                        {
                                $i[ $_k ] = $this->clean__excessive_separators( $_v, $sep );
                        }
                }
                else
                {
                        # Clean duplicates
                        $i = preg_replace( "/" . preg_quote( $sep ) . "{2,}/" , $sep , $i );

                        # Clean leading and trailing separators, i.e. trim those
                        // Doesn't work if separator is not a char but a string of chars.
                        // $i = trim( $i, $sep );
                        $i = preg_replace( "/^" . preg_quote( $sep ) . "/"  , "" , $i );
                        $i = preg_replace( "/"  . preg_quote( $sep ) . "$/" , "" , $i );
                }

                return $i;
        }


        /**
         * Returns a cleaned MD5 hash
         *
         * @param    string   Input String
         * @return   string   Parsed String
         */
        public function clean__md5_hash ( $t )
        {
                return preg_replace( "/[^a-zA-Z0-9]/", "" , substr( $t, 0, 32 ) );
        }


        /**
         * Strip slashes recursively
         *
         * @param    mixed    REFERENCE: Data to strip slashes from (a reference-argument for array_walk to work)
         * @return   mixed    Slash-stripped data
         */
        public function clean__stripslashes ( &$i )
        {
                is_array( $i )
                        ?
                        array_walk( $i, array( $this, "clean__stripslashes" ) )
                        :
                        $i = stripslashes( $i );

                return $i;
        }


        /**
         * Strip tags
         *
         * @param  mixed $i Data to strip (X)HTML tags from
         * @return mixed $i Clean data
         */
        public function clean__strip_tags ( $i, $tags_to_strip = "" )
        {
                $i = is_array( $i )
                        ?
                        array_map( array( $this, "clean__strip_tags" ), $i )
                        :
                        strip_tags( $i, $tags_to_strip );

                return $i;
        }


        /**
         * Makesafe
         *
         * @param   mixed     REFERENCE: Data to make safe
         * @param   string    KEY [used as parameter-2 in the callback function of array_walk()
         * @param   array     Additional functions to filter the value through, prior to cleaning
         * @return  mixed     VOID if $_output_flag = false; MIXED otherwise.
         */
        private function _clean__makesafe ( &$val, $key, $filters = array() )
        {

                if ( $val === '' )                                                                         // Literally empty string, integer 0 excluded
                {
                        return true;
                }

                # Let's apply additional functions, if any, to clean further
                if ( isset( $filters ) and is_array( $filters ) and count( $filters ) )
                {
                        foreach( $filters as $_filter )
                        {
                                if ( is_array( $_filter ) and is_object( $_filter[0] ) and method_exists( $_filter[0], $_filter[1] ) )
                                {
                                        $val = &$_filter[0]->$_filter[1]( $val );
                                }
                                elseif ( function_exists( $_filter ) )
                                {
                                        $val = $_filter( $val );
                                }
                                else
                                {
                                        throw new Exception ("Parameter-2 of Input::_clean__makesafe() must be a valid function/method callback!");
                                }
                        }
                }

                $val = trim( $val );
                // $val = $this->clean__stripslashes( $val );
                $val = str_replace( "&#032;" , " " , $val );

                $val = $this->clean__control_characters( $val );

                # Convert all carriage return combos
                $val = str_replace( array( '\r\n', '\n\r', '\r' ), "\n", $val );

                # Continue with cleaning...

                $val = str_replace( "&"             , "&amp;"           , $val );
                $val = str_replace( "<!--"          , "&#60;&#33;--"    , $val );
                $val = str_replace( "-->"           , "--&#62;"         , $val );
                $val = preg_replace( "/<script/i"   , "&#60;script"     , $val );
                $val = str_replace( ">"             , "&gt;"            , $val );
                $val = str_replace( "<"             , "&lt;"            , $val );
                $val = str_replace( '"'             , "&quot;"          , $val );
                $val = str_replace( '\n'            , "<br />"          , $val );                          // Convert literal newlines
                $val = str_replace( '$'             , "&#36;"           , $val );
                $val = str_replace( "!"             , "&#33;"           , $val );
                $val = str_replace( "'"             , "&#39;"           , $val );                          // IMPORTANT: It helps to increase sql query safety.

                # Convert HTML entities into friendly versions of them
                $_list_of_html_entities__from = array( "&#160;","&#161;","&#162;","&#163;","&#164;","&#165;","&#166;","&#167;","&#168;","&#169;","&#170;","&#171;","&#172;","&#173;","&#174;","&#175;","&#176;","&#177;","&#178;","&#179;","&#180;","&#181;","&#182;","&#183;","&#184;","&#185;","&#186;","&#187;","&#188;","&#189;","&#190;","&#191;","&#192;","&#193;","&#194;","&#195;","&#196;","&#197;","&#198;","&#199;","&#200;","&#201;","&#202;","&#203;","&#204;","&#205;","&#206;","&#207;","&#208;","&#209;","&#210;","&#211;","&#212;","&#213;","&#214;","&#215;","&#216;","&#217;","&#218;","&#219;","&#220;","&#221;","&#222;","&#223;","&#224;","&#225;","&#226;","&#227;","&#228;","&#229;","&#230;","&#231;","&#232;","&#233;","&#234;","&#235;","&#236;","&#237;","&#238;","&#239;","&#240;","&#241;","&#242;","&#243;","&#244;","&#245;","&#246;","&#247;","&#248;","&#249;","&#250;","&#251;","&#252;","&#253;","&#254;","&#255;","&#402;","&#913;","&#914;","&#915;","&#916;","&#917;","&#918;","&#919;","&#920;","&#921;","&#922;","&#923;","&#924;","&#925;","&#926;","&#927;","&#928;","&#929;","&#931;","&#932;","&#933;","&#934;","&#935;","&#936;","&#937;","&#x03B1;","&#946;","&#947;","&#948;","&#949;","&#950;","&#951;","&#952;","&#953;","&#954;","&#955;","&#956;","&#957;","&#958;","&#959;","&#960;","&#961;","&#962;","&#963;","&#964;","&#965;","&#966;","&#967;","&#968;","&#969;","&#977;","&#978;","&#982;","&#8226;","&#8230;","&#8242;","&#8243;","&#8254;","&#8260;","&#8472;","&#8465;","&#8476;","&#8482;","&#8501;","&#8592;","&#8593;","&#8594;","&#8595;","&#8596;","&#8629;","&#8656;","&#8657;","&#8658;","&#8659;","&#8660;","&#8704;","&#8706;","&#8707;","&#8709;","&#8711;","&#8712;","&#8713;","&#8715;","&#8719;","&#8721;","&#8722;","&#8727;","&#8730;","&#8733;","&#8734;","&#8736;","&#8743;","&#8744;","&#8745;","&#8746;","&#8747;","&#8756;","&#8764;","&#8773;","&#8776;","&#8800;","&#8801;","&#8804;","&#8805;","&#8834;","&#8835;","&#8836;","&#8838;","&#8839;","&#8853;","&#8855;","&#8869;","&#8901;","&#8968;","&#8969;","&#8970;","&#8971;","&#9001;","&#9002;","&#9674;","&#9824;","&#9827;","&#9829;","&#9830;","&#34;","&#38;","&#60;","&#62;","&#338;","&#339;","&#352;","&#353;","&#376;","&#710;","&#732;","&#8194;","&#8195;","&#8201;","&#8204;","&#8205;","&#8206;","&#8207;","&#8211;","&#8212;","&#8216;","&#8217;","&#8218;","&#8220;","&#8221;","&#8222;","&#8224;","&#8225;","&#8240;","&#8249;","&#8250;","&#8364;" );
                $_list_of_html_entities__to   = array( "&nbsp;","&iexcl;","&cent;","&pound;","&curren;","&yen;","&brvbar;","&sect;","&uml;","&copy;","&ordf;","&laquo;","&not;","&shy;","&reg;","&macr;","&deg;","&plusmn;","&sup2;","&sup3;","&acute;","&micro;","&para;","&middot;","&cedil;","&sup1;","&ordm;","&raquo;","&frac14;","&frac12;","&frac34;","&iquest;","&Agrave;","&Aacute;","&Acirc;","&Atilde;","&Auml;","&Aring;","&AElig;","&Ccedil;","&Egrave;","&Eacute;","&Ecirc;","&Euml;","&Igrave;","&Iacute;","&Icirc;","&Iuml;","&ETH;","&Ntilde;","&Ograve;","&Oacute;","&Ocirc;","&Otilde;","&Ouml;","&times;","&Oslash;","&Ugrave;","&Uacute;","&Ucirc;","&Uuml;","&Yacute;","&THORN;","&szlig;","&agrave;","&aacute;","&acirc;","&atilde;","&auml;","&aring;","&aelig;","&ccedil;","&egrave;","&eacute;","&ecirc;","&euml;","&igrave;","&iacute;","&icirc;","&iuml;","&eth;","&ntilde;","&ograve;","&oacute;","&ocirc;","&otilde;","&ouml;","&divide;","&oslash;","&ugrave;","&uacute;","&ucirc;","&uuml;","&yacute;","&thorn;","&yuml;","&fnof;","&Alpha;","&Beta;","&Gamma;","&Delta;","&Epsilon;","&Zeta;","&Eta;","&Theta;","&Iota;","&Kappa;","&Lambda;","&Mu;","&Nu;","&Xi;","&Omicron;","&Pi;","&Rho;","&Sigma;","&Tau;","&Upsilon;","&Phi;","&Chi;","&Psi;","&Omega;","&alpha;","&beta;","&gamma;","&delta;","&epsilon;","&zeta;","&eta;","&theta;","&iota;","&kappa;","&lambda;","&mu;","&nu;","&xi;","&omicron;","&pi;","&rho;","&sigmaf;","&sigma;","&tau;","&upsilon;","&phi;","&chi;","&psi;","&omega;","&thetasym;","&upsih;","&piv;","&bull;","&hellip;","&prime;","&Prime;","&oline;","&frasl;","&weierp;","&image;","&real;","&trade;","&alefsym;","&larr;","&uarr;","&rarr;","&darr;","&harr;","&crarr;","&lArr;","&uArr;","&rArr;","&dArr;","&hArr;","&forall;","&part;","&exist;","&empty;","&nabla;","&isin;","&notin;","&ni;","&prod;","&sum;","&minus;","&lowast;","&radic;","&prop;","&infin;","&ang;","&and;","&or;","&cap;","&cup;","&int;","&there4;","&sim;","&cong;","&asymp;","&ne;","&equiv;","&le;","&ge;","&sub;","&sup;","&nsub;","&sube;","&supe;","&oplus;","&otimes;","&perp;","&sdot;","&lceil;","&rceil;","&lfloor;","&rfloor;","&lang;","&rang;","&loz;","&spades;","&clubs;","&hearts;","&diams;","&quot;","&amp;","&lt;","&gt;","&OElig;","&oelig;","&Scaron;","&scaron;","&Yuml;","&circ;","&tilde;","&ensp;","&emsp;","&thinsp;","&zwnj;","&zwj;","&lrm;","&rlm;","&ndash;","&mdash;","&lsquo;","&rsquo;","&sbquo;","&ldquo;","&rdquo;","&bdquo;","&dagger;","&Dagger;","&permil;","&lsaquo;","&rsaquo;","&euro;");
                $val = str_replace( $_list_of_html_entities__from , $_list_of_html_entities__to , $val );

                # Ensure unicode chars are OK
                // @todo
                // $val = preg_replace("/&amp;(#[0-9]+|[a-z]+);/s", "&\\1;", $val );

                # Try and fix up HTML entities with missing ;
                $val = preg_replace( "/&#(\d+?)([^\d;])/i", "&#\\1;\\2", $val );

                return true;
        }


        /**
         * Performs basic cleaning (Null characters, etc) on globals
         *
         * @param   array     Incoming data to clean
         * @param   integer   Incoming data array depth
         * @return  void
         *
         * @author  concept by Matthew Mecham @ IPS; adapted by Shahriyar Imanov @ Audith
         */
        public function clean__globals ( &$data, $iteration = 0 )
        {
                # Crafty hacker could send something like &foo[][][][][][]....to kill Apache process
                # We should never have an globals array deeper than 10..
                if( $iteration >= 10 )
                {
                        return;
                }

                foreach ( $data as $k => $v )
                {
                        if ( is_array( $v ) )
                        {
                                $this->clean__globals( $data[ $k ] , ++$iteration );
                        }
                        else
                        {
                                # Null byte characters
                                $v = str_replace( chr('0') , '' , $v );
                                $v = str_replace( "\0"     , '' , $v );
                                $v = str_replace( "\x00"   , '' , $v );
                                $v = str_replace( '%00'    , '' , $v );

                                # File traversal
                                $v =  str_replace( '../' , "&#046;&#046;/" , $v );

                                # RTL override
                                $v = str_replace( '&#8238;', '' , $v );

                                $data[ $k ] = $v;
                        }
                }
        }


        /**
         * Recursively cleans keys and values and inserts them into the input array [Build 20090120]
         *
         * @param   array     Incoming data to clean
         * @param   integer   Incoming data array depth
         * @param   array     Additional functions to filter the value through, prior to cleaning
         *
         * @author concept by Matthew Mecham @ IPS; adapted by Shahriyar Imanov @ Audith
         */
        public function clean__makesafe_recursively ( $data, $iteration = 0, $filters = array() )
        {
                # Crafty hacker could send something like &foo[][][][][][]....to kill Apache process
                # We should never have an input array deeper than 10..
                if ( $iteration >= 10 )
                {
                        return $data;
                }

                if ( is_array( $data ) and count( $data ) )
                {
                        $_cleaned_data = array();
                        foreach ( $data as $k => $v )
                        {
                                # Recursion
                                if ( is_array( $v ) )
                                {
                                        //unset( $data[ $k ] );
                                        $_cleaned_data[ $this->clean__makesafe_key( $k ) ] = $this->clean__makesafe_recursively( $v, $iteration + 1, $filters );
                                }
                                # Actual cleanup
                                else
                                {
                                        //unset( $data[ $k ] );
                                        $_cleaned_data[ $this->clean__makesafe_key( $k ) ] = $this->clean__makesafe_value( $v, $filters, true );    // We need output
                                }
                        }
                }
                else
                {
                        $_cleaned_data = $this->clean__makesafe_value( $data, $filters, true );
                }

                return $_cleaned_data;
        }


        /**
         * WRAPPER for clean__makesafe(): Clean's incoming values (usually _GET, _POST)
         *
         * @param    mixed    REF: Mixed value to parse
         * @param    array    Additional functions to filter the value through, prior to cleaning
         * @param    boolean  Whether to return the result or not, defaults to FALSE
         * @return   mixed    MIXED Cleaned value if output_flag is set on; BOOLEAN otherwise
         */
        public function clean__makesafe_value ( &$val , $filters = array() , $do_output = false )
        {
                # If its an array, 'walk-through-it' recursively with Input::_clean__makesafe() ...
                if ( is_array( $val ) )
                {
                        array_walk_recursive( $val, array( $this, "_clean__makesafe" ), $filters );
                }
                # ... otherwise, just apply Input::clean__makesafe() to it.
                else
                {
                        $this->_clean__makesafe( $val, null, $filters );
                }

                # If explicit return is requested, comply - otherwise go Boolean.
                if ( $do_output )
                {
                        return $val;
                }
                return true;
        }


        /**
         * Clean's incoming-array keys (usually _GET, _POST), ensures no funny business with form elements [Build 20080120]
         *
         * @param    String  Key name
         * @return   String  Cleaned key name
         */
        public function clean__makesafe_key ( $key )
        {
                if ( $key === "" )
                {
                        return "";
                }

                $key = htmlspecialchars( urldecode($key) );
        $key = str_replace( ".." , "" , $key );
        $key = preg_replace( "/\_\_(.+?)\_\_/" , "" , $key );
        $key = preg_replace( "/^([\w\.\-\_]+)$/" , "$1" , $key );

                return $key;
        }


        /**
         * Clean a string to remove all non-alphanumeric characters
         *
         * @param   string   Input string
         * @param   string   Additional characters to preserve
         * @return  string   Parsed string
         */
        public function clean__makesafe_alphanumerical ( $val , $_characters_to_preserve = "" )
        {
                if ( ! empty( $_characters_to_preserve ) )
                {
                        $_characters_to_preserve = preg_quote( $_characters_to_preserve, "/" );
                }

                $val = preg_replace("/&(?:#[0-9]+|[a-z]+);/i" , "" , $val );
                $val = preg_replace( "/[^a-z0-9\-\_" . $_characters_to_preserve . "]/i", "" , $val );

                return $val;
        }


        /**
         * Clean a string to remove all non-arithmetic characters [non- numericals, arithmetic operators and parentheses] and then
         * makes sure the final expression is a valid mathematical/arithmetic expression, PHP-wise. Usually for eval()'s...
         * IMPORTANT NOTE: PHP math functions are not supported!
         *
         * @param   string    Input String
         * @param   boolean   Whether to allow decimal-point in regex control or not
         * @param   boolean   Whether to perform enclosing parentheses check or not
         * @return  mixed     Parsed String on success; FALSE otherwise
         */
        public function clean__makesafe_mathematical ( $val , $allow_decimal_point = false, $check_enclosing_parentheses = false )
        {
                if ( $check_enclosing_parentheses )
                {
                        if ( ! $this->check_enclosing_parentheses( $val ) )
                        {
                                return false;
                        }
                }

                $val = preg_replace("/&(?:#[0-9]+|[a-z]+);/i" , "" , $val );          // Getting rid of all HTML entities
                if ( $allow_decimal_point )
                {
                        $val = preg_replace( '#[^0-9\-\+\*\/\(\)\.]+#' , "" , $val );     // Remove non numericals, leave decimal-point
                        $val = preg_replace( '#(?<=\d)\.(?!\d)#' , "" , $val );           // Remove trailing decimal points (e.g. "0." )
                        $val = preg_replace( '#(?<!\d)\.(?=\d)#' , "" , $val );           // Remove leader decimal points (e.g. ".0" )
                }
                else
                {
                        $val = preg_replace( '#[^0-9\-\+\*\/\(\)]+#' , "" , $val );       // Remove non numericals
                }
                $val = preg_replace( '#^[\+\*\/]+#' , "" , $val );                    // Remove leading arithmetics, leave leading (-) for signs
                $val = preg_replace( '#[\-\+\*\/]+$#' , "" , $val );                  // Remove trailing arithmetics
                $val = preg_replace( '#(?<=\()[^0-9\-]+(?=\d)#' , "" , $val );        // Remove leading arithmetics [within parentheses], leave leading (-) for signs
                $val = preg_replace( '#(?<=\d)[^0-9]+(?=\))#' , "" , $val );          // Remove trailing arithmetics [within parentheses]

                return $val;
        }


        /**
         * Convert HTML line break tags to \n
         *
         * @param    string   Input text
         * @return   string   Parsed text
         */
        public function br2nl ()
        {
                $t = str_replace( array( "\r", "\n" ), '', $t );
                $t = str_replace( array( "<br />", "<br>" ), "\n", $t );
                return $t;
        }


        /**
         * Base64 encode for URLs
         *
         * @param    string    Data
         */
        public function base64_encode_urlsafe ( $data )
        {
                return strtr( base64_encode( $data ), '+/=', '-_,' );;
        }

        /**
         * Base64 decode for URLs
         *
         * @param    string    Data
         * @return   string    Data
         */
        public function base64_decode_urlsafe ( $data )
        {
                return base64_decode( strtr( $data, '-_,', '+/=' ) );
        }


        /**
         * Converts accented characters into their plain alphabetic counterparts
         *
         * @param    string    Raw text
         * @return   string    Cleaned text
         *
         * @author              $Author: matt $
         * @copyright   (c) 2001 - 2009 Invision Power Services, Inc.
         * @license             http://www.invisionpower.com/community/board/license.html
         * @package             Invision Power Board
         */
        public function convert_accents ( $string )
        {
                if ( ! preg_match('/[\x80-\xff]/', $string) )
                {
                        return $string;
                }

                $_chr = array(
                                /* Latin-1 Supplement */
                                chr(195).chr(128) => 'A', chr(195).chr(129) => 'A',
                                chr(195).chr(130) => 'A', chr(195).chr(131) => 'A',
                                chr(195).chr(132) => 'A', chr(195).chr(133) => 'A',
                                chr(195).chr(135) => 'C', chr(195).chr(136) => 'E',
                                chr(195).chr(137) => 'E', chr(195).chr(138) => 'E',
                                chr(195).chr(139) => 'E', chr(195).chr(140) => 'I',
                                chr(195).chr(141) => 'I', chr(195).chr(142) => 'I',
                                chr(195).chr(143) => 'I', chr(195).chr(145) => 'N',
                                chr(195).chr(146) => 'O', chr(195).chr(147) => 'O',
                                chr(195).chr(148) => 'O', chr(195).chr(149) => 'O',
                                chr(195).chr(150) => 'O', chr(195).chr(153) => 'U',
                                chr(195).chr(154) => 'U', chr(195).chr(155) => 'U',
                                chr(195).chr(156) => 'U', chr(195).chr(157) => 'Y',
                                chr(195).chr(159) => 's', chr(195).chr(160) => 'a',
                                chr(195).chr(161) => 'a', chr(195).chr(162) => 'a',
                                chr(195).chr(163) => 'a', chr(195).chr(164) => 'a',
                                chr(195).chr(165) => 'a', chr(195).chr(167) => 'c',
                                chr(195).chr(168) => 'e', chr(195).chr(169) => 'e',
                                chr(195).chr(170) => 'e', chr(195).chr(171) => 'e',
                                chr(195).chr(172) => 'i', chr(195).chr(173) => 'i',
                                chr(195).chr(174) => 'i', chr(195).chr(175) => 'i',
                                chr(195).chr(177) => 'n', chr(195).chr(178) => 'o',
                                chr(195).chr(179) => 'o', chr(195).chr(180) => 'o',
                                chr(195).chr(181) => 'o', chr(195).chr(182) => 'o',
                                chr(195).chr(182) => 'o', chr(195).chr(185) => 'u',
                                chr(195).chr(186) => 'u', chr(195).chr(187) => 'u',
                                chr(195).chr(188) => 'u', chr(195).chr(189) => 'y',
                                chr(195).chr(191) => 'y',
                                /* Latin Extended-A */
                                chr(196).chr(128) => 'A', chr(196).chr(129) => 'a',
                                chr(196).chr(130) => 'A', chr(196).chr(131) => 'a',
                                chr(196).chr(132) => 'A', chr(196).chr(133) => 'a',
                                chr(196).chr(134) => 'C', chr(196).chr(135) => 'c',
                                chr(196).chr(136) => 'C', chr(196).chr(137) => 'c',
                                chr(196).chr(138) => 'C', chr(196).chr(139) => 'c',
                                chr(196).chr(140) => 'C', chr(196).chr(141) => 'c',
                                chr(196).chr(142) => 'D', chr(196).chr(143) => 'd',
                                chr(196).chr(144) => 'D', chr(196).chr(145) => 'd',
                                chr(196).chr(146) => 'E', chr(196).chr(147) => 'e',
                                chr(196).chr(148) => 'E', chr(196).chr(149) => 'e',
                                chr(196).chr(150) => 'E', chr(196).chr(151) => 'e',
                                chr(196).chr(152) => 'E', chr(196).chr(153) => 'e',
                                chr(196).chr(154) => 'E', chr(196).chr(155) => 'e',
                                chr(196).chr(156) => 'G', chr(196).chr(157) => 'g',
                                chr(196).chr(158) => 'G', chr(196).chr(159) => 'g',
                                chr(196).chr(160) => 'G', chr(196).chr(161) => 'g',
                                chr(196).chr(162) => 'G', chr(196).chr(163) => 'g',
                                chr(196).chr(164) => 'H', chr(196).chr(165) => 'h',
                                chr(196).chr(166) => 'H', chr(196).chr(167) => 'h',
                                chr(196).chr(168) => 'I', chr(196).chr(169) => 'i',
                                chr(196).chr(170) => 'I', chr(196).chr(171) => 'i',
                                chr(196).chr(172) => 'I', chr(196).chr(173) => 'i',
                                chr(196).chr(174) => 'I', chr(196).chr(175) => 'i',
                                chr(196).chr(176) => 'I', chr(196).chr(177) => 'i',
                                chr(196).chr(178) => 'IJ',chr(196).chr(179) => 'ij',
                                chr(196).chr(180) => 'J', chr(196).chr(181) => 'j',
                                chr(196).chr(182) => 'K', chr(196).chr(183) => 'k',
                                chr(196).chr(184) => 'k', chr(196).chr(185) => 'L',
                                chr(196).chr(186) => 'l', chr(196).chr(187) => 'L',
                                chr(196).chr(188) => 'l', chr(196).chr(189) => 'L',
                                chr(196).chr(190) => 'l', chr(196).chr(191) => 'L',
                                chr(197).chr(128) => 'l', chr(197).chr(129) => 'L',
                                chr(197).chr(130) => 'l', chr(197).chr(131) => 'N',
                                chr(197).chr(132) => 'n', chr(197).chr(133) => 'N',
                                chr(197).chr(134) => 'n', chr(197).chr(135) => 'N',
                                chr(197).chr(136) => 'n', chr(197).chr(137) => 'N',
                                chr(197).chr(138) => 'n', chr(197).chr(139) => 'N',
                                chr(197).chr(140) => 'O', chr(197).chr(141) => 'o',
                                chr(197).chr(142) => 'O', chr(197).chr(143) => 'o',
                                chr(197).chr(144) => 'O', chr(197).chr(145) => 'o',
                                chr(197).chr(146) => 'OE',chr(197).chr(147) => 'oe',
                                chr(197).chr(148) => 'R',chr(197).chr(149) => 'r',
                                chr(197).chr(150) => 'R',chr(197).chr(151) => 'r',
                                chr(197).chr(152) => 'R',chr(197).chr(153) => 'r',
                                chr(197).chr(154) => 'S',chr(197).chr(155) => 's',
                                chr(197).chr(156) => 'S',chr(197).chr(157) => 's',
                                chr(197).chr(158) => 'S',chr(197).chr(159) => 's',
                                chr(197).chr(160) => 'S', chr(197).chr(161) => 's',
                                chr(197).chr(162) => 'T', chr(197).chr(163) => 't',
                                chr(197).chr(164) => 'T', chr(197).chr(165) => 't',
                                chr(197).chr(166) => 'T', chr(197).chr(167) => 't',
                                chr(197).chr(168) => 'U', chr(197).chr(169) => 'u',
                                chr(197).chr(170) => 'U', chr(197).chr(171) => 'u',
                                chr(197).chr(172) => 'U', chr(197).chr(173) => 'u',
                                chr(197).chr(174) => 'U', chr(197).chr(175) => 'u',
                                chr(197).chr(176) => 'U', chr(197).chr(177) => 'u',
                                chr(197).chr(178) => 'U', chr(197).chr(179) => 'u',
                                chr(197).chr(180) => 'W', chr(197).chr(181) => 'w',
                                chr(197).chr(182) => 'Y', chr(197).chr(183) => 'y',
                                chr(197).chr(184) => 'Y', chr(197).chr(185) => 'Z',
                                chr(197).chr(186) => 'z', chr(197).chr(187) => 'Z',
                                chr(197).chr(188) => 'z', chr(197).chr(189) => 'Z',
                                chr(197).chr(190) => 'z', chr(197).chr(191) => 's',
                                /* Euro Sign */
                                chr(226).chr(130).chr(172) => 'E',
                                /* GBP (Pound) Sign */
                                chr(194).chr(163) => ''
                        );

                $string = strtr($string, $_chr);

                return $string;
        }


        /**
         * Convert Windows/MacOS9 line delimiters to their Unix counterpart
         *
         * @param    string    Input String
         * @return   string    Parsed String
         */
        public function convert_line_delimiters_to_unix ( $t )
        {
                # Windows
                $t = str_replace( "\r\n" , "\n", $t );

                # Mac OS 9
                $t = str_replace( "\r"   , "\n", $t );

                return $t;
        }


        /**
         * Convert a string between charsets
         *
         * @param   string    Input String
         * @param   string    Current char set
         * @param   string    Destination char set
         * @return  string    Parsed string
         * @todo    [Future] If an error is set in classConvertCharset, show it or log it somehow
         */
        public function convert_encoding ( $text, $from_encoding, $to_encoding = "UTF-8" )
        {
                $from_encoding = strtolower( $from_encoding );
                $t             = $text;

                //-----------------
                // Not the same?
                //-----------------

                if ( $to_encoding == $from_encoding )
                {
                        return $t;
                }

                if ( ! is_object( $this->encoding_converter ) )
                {
                        require_once( PATH_LIBS . '/IPS_Sources/classConvertCharset.php' );
                        $this->encoding_converter = new classConvertCharset();

                        /*if ( function_exists( 'mb_convert_encoding' ) )
                        {
                                $this->encoding_converter->method = 'mb';
                        }
                        elseif ( function_exists( 'iconv' ) )
                        {
                                $this->encoding_converter->method = 'iconv';
                        }
                        elseif ( function_exists( 'recode_string' ) )
                        {
                                $this->encoding_converter->method = 'recode';
                        }
                        else
                        {
                        */      $this->encoding_converter->method = 'internal';
                        //}
                }

                $text = $this->encoding_converter->convertEncoding( $text, $from_encoding, $to_encoding );

                return $text ? $text : $t;
        }


        /**
         * Similar to htmlspecialchars(), but is more careful with entities in &#123; format.
         *
         * @param    string    Input String
         * @return   string    Parsed String
         */
        public function htmlspecialchars ( $t )
        {
                $t = preg_replace("/&(?!#[0-9]+;)/s", '&amp;', $t ); // Use forward look up to only convert & not &#123;
                $t = str_replace( "<", "&lt;"  , $t );
                $t = str_replace( ">", "&gt;"  , $t );
                $t = str_replace( '"', "&quot;", $t );
                $t = str_replace( "'", "&#39;", $t );

                return $t;
        }


        /**
         * Get the true length of a multi-byte character string
         *
         * @param    string     Input String
         * @return   integer    String length
         */
        public function mb_strlen( $t )
        {
                if ( function_exists( 'mb_list_encodings' ) )
                {
                        $encodings = mb_list_encodings();

                        if ( in_array( "UTF-8", array_map( 'strtoupper', $encodings ) ) )
                        {
                                if ( mb_internal_encoding() != 'UTF-8' )
                                {
                                        mb_internal_encoding( "UTF-8" );
                                }
                                return mb_strlen( $t );
                        }
                }

                return strlen( preg_replace("/&#([0-9]+);/", "-", $t ) );
        }


        /**
         * Convert text for use in form elements (text-input and textarea)
         *
         * @param    mixed      Input String/Array (of strings)
         * @return   string     Parsed String
         */
        public function raw2form ( & $t )
        {
                if ( is_array( $t ) )
                {
                        array_walk( $t, array( $this, "raw2form" ) );
                }
                else
                {
                        $t = str_replace( '$', "&#36;", $t);

                        /*
                        if ( MAGIC_QUOTES_GPC_ON )
                        {
                                $t = stripslashes($t);
                        }
                        */

                        $t = preg_replace( "/\\\(?!&amp;#|\?#)/", "&#92;", $t );

                        return $t;
                }
        }


        /**
         * Convert text for use in form elements (text-input and textarea)
         *
         * @param    string   Input String
         * @return   string   Parsed String
         */
        public function text2form ( &$t )
        {
                if ( is_array( $t ) )
                {
                        array_walk( $t, array( $this, "text2form" ) );
                }
                else
                {
                        $t = str_replace( "&#38;"  , "&", $t );
                        $t = str_replace( "&#60;"  , "<", $t );
                        $t = str_replace( "&#62;"  , ">", $t );
                        $t = str_replace( "&#34;"  , '"', $t );
                        $t = str_replace( "&#39;"  , "'", $t );
                        $t = str_replace( "&#33;"  , "!", $t );
                        $t = str_replace( "&#46;&#46;/" , "../", $t );

                        /*
                        if ( IN_ACP )
                        {
                                $t = str_replace( '&#092;' ,'\\', $t );
                        }
                        */
                }
        }

        /**
         * Cleaned form data back to text
         *
         * @param    string    Input String
         * @return   string    Parsed String
         */
        public function form2text ( &$t )
        {
                if ( is_array( $t ) )
                {
                        array_walk( $t, array( $this, "form2text" ) );
                }
                else
                {
                        $t = str_replace( "&" , "&#038;"  , $t );
                        $t = str_replace( "<" , "&#060;"  , $t );
                        $t = str_replace( ">" , "&#062;"  , $t );
                        $t = str_replace( '"' , "&#034;"  , $t );
                        $t = str_replace( "'" , '&#039;'  , $t );

                        /*
                        if ( IN_ACP )
                        {
                                $t = str_replace( "\\", "&#092;" , $t );
                        }
                        */
                }
        }


        /**
         * Converts string to hexadecimal
         *
         * @param    string   String to convert
         * @return   string   Resulting hexadecimal value
         */
        public function strhex ( $string )
        {
                $hex = "";
                for ( $i=0; $i < strlen( $string ); $i++ )
                {
                        $hex .= dechex( ord( $string[ $i ] ) );
                }
                return $hex;
        }


        /**
         * Converts hexadecimal to string
         *
         * @param    string   Hexadecimal to convert
         * @return   string   Resulting string
         */
        public function hexstr ( $hex )
        {
                $string = "";
                for ( $i=0; $i < strlen( $hex ) - 1; $i += 2 )
                {
                        $string .= chr( hexdec( $hex[ $i ] . $hex[ $i + 1 ] ) );
                }
                return $string;
        }


        /**
         * Make an SEO title for use in the URL
         *
         * @param    string    Raw SEO title or text
         * @return   string    Cleaned up SEO title
         */
        public function make_seo_title ( $text )
        {
                if ( ! $text )
                {
                        return "";
                }

                // $text = str_replace( array( '`', ' ', '+', '.', '?', '_', '#' ), '-', $text );

                # Doesn't need converting?
                /*
                if ( preg_match( "#^[a-zA-Z0-9\-]+$#", $_text ) )
                {
                        $_text = $this->clean__excessive_separators( $_text, "-" );
                        return $_text;
                }
                */

                # Strip all HTML tags first
                $text = strip_tags( $text );

                # Preserve %data
                $text = preg_replace( '#%([a-fA-F0-9][a-fA-F0-9])#', '-xx-$1-xx-', $text );
                $text = str_replace( array( '%', '`' ), '', $text );
                $text = preg_replace( '#-xx-([a-fA-F0-9][a-fA-F0-9])-xx-#', '%$1', $text );

                # Convert accented chars
                $text = $this->convert_accents( $text );

                # Convert it
                if ( function_exists('mb_strtolower') )
                {
                        $text = mb_strtolower( $text, "UTF-8" );
                }

                $text = $this->utf8_encode_to_specific_length( $text, 250 );

                # Finish off
                $text = strtolower( $text );

                $text = preg_replace( '#&.+?;#'        , '', $text );
                $text = preg_replace( '#[^%a-z0-9 _-]#', '', $text );

                $text = str_replace( array( '`', ' ', '+', '.', '?', '_', '#' ), '-', $text );
                $text = $this->clean__excessive_separators( $text, "-" );

                return ( $text ) ? $text : '-';
        }


        /**
         * Check if the string is valid for the specified encoding - Build 20080614
         * @param   string     Byte stream to check
         * @param   string     Expected encoding
         * @param   string     Encoding type for double-checking, since mb_check_encoding() function of MBString extension sometimes does wrong encoding checks
         * @return  boolean    Returns 1 on success, 0 on failure and -1 if MBString extension is not available
         */
        public function mbstring_check_encoding ( $string, $target_encoding, $secondary_encoding = null )
        {
                if ( !in_array( "mbstring", $this->Registry->config['runtime']['loaded_extensions'] ) )
                {
                        return -1;
                }

                if ( $secondary_encoding )
                {
                        if (
                                mb_check_encoding( $string, $target_encoding )
                                and
                                mb_substr_count( $string, '?', $secondary_encoding ) == mb_substr_count( mb_convert_encoding( $string, $target_encoding, $secondary_encoding ), '?', $target_encoding )
                        )
                        {
                                return 1;
                        }
                        else
                        {
                                return 0;
                        }
                }
                else
                {
                        if ( mb_check_encoding( $string, $target_encoding ) )
                        {
                                return 1;
                        }
                        else
                        {
                                return 0;
                        }
                }
        }


        /**
         * Fetches environmental variable by key - Build 20080824
         * @param  string $key  ENV var key to fetch a value for
         * @return string       Environment variable value requested
         */
        public function my_getenv ( $key )
        {
                if ( is_array( $_SERVER ) and count( $_SERVER ) )
                {
                        if ( isset( $_SERVER[ $key ] ) )
                        {
                                $return = $_SERVER[ $key ];
                        }
                }

                if ( ! isset( $return ) or empty( $return ) )
                {
                        $return = getenv( $key );
                }

                return $return;
        }


        /**
         * Get a cookie
         * @param   String   Cookie name
         * @return  Mixed    Cookie value on success, FALSE on failure
         */
        public function my_getcookie ( $name )
        {
                if ( isset( $this->_cookie_set[ $name ] ) )
                {
                        return $this->_cookie_set[ $name ];
                }

                $cookie_id = $this->Registry->config['cookies']['cookie_id'];

                if ( isset( $_COOKIE[ $cookie_id . $name ] ) )
                {
                        return $this->clean__makesafe_value( $_COOKIE[ $cookie_id . $name], array( "urldecode" ), true );
                }
                else
                {
                        return false;
                }
        }


        /**
         * My setcookie() function
         * @param   string    Cookie name
         * @param   mixed     Cookie value
         * @param   integer   Is cookie sticky (lifespan = 1 year)
         * @param   integer   Cookie lifetime
         * @return  void
         */
        public function my_setcookie ( $name, $value = "", $is_sticky = 1, $expires_x_days = 0 )
        {
                # Auto-serialize arrays
                if ( is_array( $value ) )
                {
                        $value = serialize( $value );
                }

                # Expiry time
                if ( $is_sticky )
                {
                        $lifetime   = 86400 * 365;
                        $expires_at = UNIX_TIME_NOW + $lifetime;
                }
                else if ( $expires_x_days and is_numeric( $expires_x_days ) )
                {
                        $lifetime   = 86400 * $expires_x_days;
                        $expires_at = UNIX_TIME_NOW + $lifetime;
                }
                else
                {
                        $expires_at = false;
                }

                # Cookie domain and path
                $cookie_id       = $this->Registry->config['cookies']['cookie_id'];
                $cookie_domain   = $this->Registry->config['cookies']['cookie_domain'];
                $cookie_path     = $this->Registry->config['cookies']['cookie_path'];
                $cookie_secure   = false;
                $cookie_httponly = false;

                if ( in_array( $name, $this->sensitive_cookies ) )
                {
                        $cookie_httponly = true;
                }

                # Set cookie
                if ( version_compare( PHP_VERSION, "5.2", "<" ) )
                {
                        # Prior to PHP 5.2
                        if ( $cookie_domain )
                        {
                                # Prior to PHP 5.2 there no 'HttpOnly' parameter for setcookie() function, so we set sensitive cookies through HTTP headers.
                                header(
                                                "Set-Cookie: " . rawurlencode( $cookie_id . $name ) . "=" . rawurlencode( $value )
                                                . ( empty( $lifetime )       ? "" : "; Max-Age=" . $expires_at     )
                                                . ( empty( $cookie_path )    ? "" : "; path="    . $cookie_path    )
                                                . ( empty( $cookie_domain )  ? "" : "; domain="  . $cookie_domain  )
                                                . ( !$cookie_secure          ? "" : "; secure"                     )
                                                . ( !$cookie_httponly        ? "" : "; HttpOnly"                   )
                                                , false
                                        );
                        }
                        else
                        {
                                header(
                                                "Set-Cookie: " . rawurlencode( $cookie_id . $name ) . "=" . rawurlencode( $value )
                                                . ( empty( $lifetime )       ? "" : "; Max-Age=" . $expires_at     )
                                                . ( empty( $cookie_path )    ? "" : "; path="    . $cookie_path    )
                                                . ( !$cookie_secure          ? "" : "; secure"                     )
                                                . ( !$cookie_httponly        ? "" : "; HttpOnly"                   )
                                                , false
                                        );
                        }
                }
                else
                {
                        # For PHP 5.2 and later
                        setcookie( $cookie_id.$name, $value, $expires_at, $cookie_path, $cookie_domain, $cookie_secure, $cookie_httponly );
                }

                # Internal Cookie-set
                $this->_cookie_set[ $name ] = $value;
        }


        /**
         * My parse_url() that parses current REQUEST_URI; additionally, it makes sure that working domain is valid - redirects to valid one otherwise
         *
         * @return   mixed   Array of parsed URL or FALSE on failure
         */
        private function my_parse_url ()
        {
                $_url  = ( empty( $_SERVER['HTTPS'] ) or $_SERVER['HTTPS'] == 'off' ) ? "http://" : "https://";
                $_url .= $_SERVER['HTTP_HOST'] ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'];
                $_url .= $_SERVER['REQUEST_URI'];
                $_parsed_url = parse_url( $_url );
                $_parsed_url['path'] = trim( $_parsed_url['path'], '\/' );
                $_parsed_url['path_exploded'] = explode( "/" , $_parsed_url['path'] );
                $_parsed_url['path'] = "/" . $_parsed_url['path'];

                $_parsed_url['request_uri'] =
                                $_parsed_url['scheme'] . "://" .
                                ( ( @$_parsed_url['user'] and @$_parsed_url['pass'] ) ? $_parsed_url['user'] . ":" . $_parsed_url['pass'] . "@" : "" ) .
                                $this->Registry->config['url']['hostname'][ $_parsed_url['scheme'] ] .
                                $_parsed_url['path'] .
                                ( @$_parsed_url['query'] ? "?" . $_parsed_url['query'] : "" );

                # Redirect to default domain-name if request was sent to different domain
                if ( $_parsed_url['host'] != $this->Registry->config['url']['hostname'][ $_parsed_url['scheme'] ] )
                {
                        $this->Registry->logger__do_log( "Registry: Request redirection to location: " . $_parsed_url['request_uri'] , "INFO" );
                        //$this->Registry->http_redirect( $_parsed_url['request_uri'] , 301 );
                }

                return $_parsed_url;
        }


        /**
         * Validates file extension by checking its contents in a BINARY level
         * @param    string   FULL-ABSOLUTE path to file
         * @return   mixed    TRUE on success; FALSE or RESULT CODES otherwise
         * RESULT CODES:
         *     "IS_NOT_FILE"       - Either it is not a regular file, or it does not exist at all
         *     "IS_NOT_READABLE"   - File is not READABLE
         *     "FILETYPE_INVALID"  - No such filetype-record was found in our MIMELIST
         */
        public function file__extension__do_validate ( $full_path_to_file )
        {
                //----------
                // Prelim
                //----------

                # Does it exist and is it a regular file?
                if ( is_file( $full_path_to_file ) !== true )
                {
                        if ( IN_DEV )
                        {
                                $this->Registry->logger__do_log( __CLASS__ . "::" . __METHOD__ . " - " . $full_path_to_file . " is NOT a REGULAR FILE or does NOT EXIST at all!" , "ERROR" );
                        }
                        return "IS_NOT_FILE";
                }

                # Is it readable?
                if ( is_readable( $full_path_to_file ) !== true )
                {
                        if ( IN_DEV )
                        {
                                $this->Registry->logger__do_log( __CLASS__ . "::" . __METHOD__ . " - Cannot READ file: " . $full_path_to_file , "ERROR" );
                        }
                        return "IS_NOT_READABLE";
                }

                $_file_content = null;
                $_file_path__parsed = pathinfo( $full_path_to_file );
                $_file_path__parsed['extension'] = strtolower( $_file_path__parsed['extension'] );

                # MIMELIST cache
                $_mimelist_cache = $this->Registry->Cache->cache__do_get_part( "mimelist" , "by_ext" );

                //-----------------
                // Continue...
                //-----------------

                if ( ! isset( $_mimelist_cache[ $_file_path__parsed['extension'] ] ) )
                {
                        if ( IN_DEV )
                        {
                                $this->Registry->logger__do_log( __CLASS__ . "::" . __METHOD__ . " - " . $_file_path__parsed['extension'] . " - NO SUCH FILETYPE IN our MIMELIST records!" , "ERROR" );
                        }
                        return "FILETYPE_INVALID";
                }

                if ( ! empty( $_mimelist_cache[ $_file_path__parsed['extension'] ]['_signatures'] ) )
                {
                        foreach ( $_mimelist_cache[ $_file_path__parsed['extension'] ]['_signatures'] as $_sigs )
                        {
                                # file_get_contents() parameters
                                $_offset = $_sigs['type_hex_id_offset'];
                                $_length = strlen( $_sigs['type_hex_id'] ) / 2;          // 'FF' as a string is 2-bytes-long, as a hex-value 1-byte-long
                                $_file_contents = file_get_contents( $full_path_to_file , FILE_BINARY , null , $_offset , $_length );
                                if ( strtoupper( bin2hex( $_file_contents ) ) == $_sigs['type_hex_id'] )
                                {
                                        return true;
                                }
                        }
                }
                else
                {
                        # This extension does not have signatures in our records, so skip the check
                        return true;
                }

                if ( IN_DEV )
                {
                        $this->Registry->logger__do_log( __CLASS__ . "::" . __METHOD__ . " - File: " . $full_path_to_file . " FAILED VALIDATION!" , "ERROR" );
                }
                return false;
        }


        /**
         * Attaches a suffix to filename (prior to extension)
         *
         * @param     string     Absolute or relative filepath
         * @param     string     Suffix to be attached
         * @return    string     Final filepath
         */
        public function file__filename__attach_suffix ( $absolute_or_relative_filepath , $suffix )
        {
                return preg_replace( '/(\.[a-z0-9]+)$/i' , $suffix . "\\1" , $absolute_or_relative_filepath );
        }


        /**
         * Calculates filesize : >4GB savvy :)
         * NOTE: This method is slower than standard filesize(), so use only when necessary
         *
         * @param    string    Path to file
         * @return   mixed     (float) Filesize parsed on success; (boolean) FALSE otherwise
         */
        public function file__filesize__do_get ( $path )
        {
                if ( file_exists( $path ) and is_file( $path ) )
                {
                        $_filesize = filesize( $path );
                        if ( $_filesize < 0 )
                        {
                                if ( ! strtolower( substr( PHP_OS, 0, 3 ) ) == 'win' )
                                {
                                        $_filesize = trim( exec( "stat -c%s " . $path ) );
                                }
                                else
                                {
                                        $_filesize = trim( exec( "for %v in (\"" . $path . "\") do @echo %~zv" ) );
                                }
                        }
                        settype( $_filesize , "float" );
                        return $_filesize;
                }
                else
                {
                        return false;
                }
        }


        /**
         * Raw filesize parsing
         *
         * @param     string    Filesize to parse
         * @return    mixed     (array) Parsed filesize with correct suffix on success; (boolean) FALSE otherwise
         */
        public function file__filesize__do_parse ( $filesize )
        {
                //-------------------------------------------------------------------------------------
                // Is it in bytes, or does it use decimal-suffixes ("K" for kilo, "Ki" for kibi, etc)
                //-------------------------------------------------------------------------------------

                $filesize = preg_replace( '/[^\d\.ikmgtpe]/i' , "" , $filesize );
                if ( ! preg_match( '/^(?P<coefficient>[\d]+\.?[\d]*)\s*(?P<suffix>[a-z]{0,2})$/i' , $filesize , $_matches ) )
                {
                        return false;
                }

                # Fix Suffixes
                switch ( strtolower( $_matches['suffix'] ) )
                {
                        case 'k':
                                $_matches['suffix'] = " Ki";
                                break;
                        case 'm':
                                $_matches['suffix'] = " Mi";
                                break;
                        case 'g':
                                $_matches['suffix'] = " Gi";
                                break;
                        case 't':
                                $_matches['suffix'] = " Ti";
                                break;
                        case 'p':
                                $_matches['suffix'] = " Pi";
                                break;
                        case 'e':
                                $_matches['suffix'] = " Ei";
                                break;
                }

                if ( $_matches['coefficient'] > 900 )                                  // In order to show the coefficients < 1
                {
                        # Update suffixes
                        switch ( strtolower( $_matches['suffix'] ) )
                        {
                                case 'ki':
                                        $_matches['suffix'] = " Mi";
                                        break;
                                case 'mi':
                                        $_matches['suffix'] = " Gi";
                                        break;
                                case 'gi':
                                        $_matches['suffix'] = " Ti";
                                        break;
                                case 'ti':
                                        $_matches['suffix'] = " Pi";
                                        break;
                                case 'pi':
                                        $_matches['suffix'] = " Ei";
                                        break;
                                default:
                                        $_matches['suffix'] = " Ki";
                                        break;
                        }
                        $_matches['coefficient'] /= 1024;
                        return $this->file__filesize__do_parse( $_matches['coefficient'] . $_matches['suffix'] );
                }
                # Base-code for Recursion
                else
                {
                        return sprintf( "%.2f" , $_matches['coefficient'] ) . " " . $_matches['suffix'];
                }
        }


        /**
         * Determines the type of file according to its MIME-type.
         *
         * @param    string    MIME-type
         * @return   string    File-type - one of the following: audio, video, image, application
         */
        public function file__filetype__do_parse ( $mime )
        {
                $_mime_exploded = explode( "/" , $mime );
                return $_mime_exploded[0];
        }


        /**
         * Safe fseek() - presumably should work with >2Gb files as well, but failed so far
         *
         * @param   resource   File handler, typically created by fopen()
         * @param   integer    Position to seek
         * @return  integer    0 on success, -1 otherwise
         */
        public function file__fseek_safe ( $file_handler , $position )
        {
                if ( ! is_resource( $file_handler ) )
                {
                        return -1;
                }

                fseek( $file_handler , 0 , SEEK_SET );

                if ( bccomp( $position , PHP_INT_MAX ) != 1 )
                {
                        return fseek( $file_handler , $position , SEEK_SET );
                }

                $t_offset  = PHP_INT_MAX;
                $position -= $t_offset;

                while ( fseek( $file_handler , $t_offset , SEEK_CUR ) === 0 )
                {
                        if ( bccomp( $position , PHP_INT_MAX ) == 1 )
                        {
                                $t_offset   = PHP_INT_MAX;
                                $position  -= $t_offset;
                        }
                        elseif ( $position > 0 )
                        {
                                $t_offset   = $position;
                                $position   = 0;
                        }
                        else
                        {
                                return 0;
                        }
                }

                return -1;
        }


        /**
         * Manipulates the current QUERY_STRING (adds, alters, removes values)
         *
         * @param   array    Parameter to add/alter/remove in format array( 'key' => string [ , 'value' => mixed ] )
         * @param   string   What to do.
         * @param   boolean  Whether to update $this->query_string_* properties or not
         * @return  mixed    (boolean) FALSE if error, (string) formatted QUERY_STRING otherwise
         */
        public function query_string__do_process ( $parameter , $action = "" , $_do_update_internals = false )
        {
                if (
                        ! is_array( $parameter )
                        or
                        (
                                is_array( $parameter )
                                and
                                (
                                        # 'alter_add' requires both KEY and VALUE pairs to exist
                                        (
                                                in_array( $action , array( "alter_add" , "+" ) )
                                                and
                                                (
                                                        ! isset( $parameter['key'] )
                                                        or
                                                        ! isset( $parameter['value'] )
                                                )
                                        )
                                        or
                                        # 'delete' requires KEY to exist
                                        (
                                                in_array( $action , array( "delete" , "-" ) )
                                                and
                                                ! isset( $parameter['key'] )
                                        )
                                )
                        )
                )
                {
                        return false;
                }
                else
                {
                        $_parameter['key'] = $this->clean__makesafe_key( $parameter['key'] );
                        $_parameter['value'] = $this->clean__makesafe_value( $parameter['value'] , array("urlencode") , true );
                }

                if ( preg_match( '/^(?P<key>[^\[\]]+)(?:\[(?P<index>[^\[\]]+)\])?$/i' , $_parameter['key'], $_array_matches ) )
                {
                        $_new_query_string = $this->get;
                        $_what_to_match_in_query_string = null;
                        if ( isset( $_array_matches['key'] ) )
                        {
                                if ( isset( $_array_matches['index'] ) )
                                {
                                        $_what_to_match_in_query_string =& $_new_query_string[ $_array_matches['key'] ][ $_array_matches['index'] ];
                                }
                                else
                                {
                                        $_what_to_match_in_query_string =& $_new_query_string[ $_array_matches['key'] ];
                                }
                        }
                }

                switch ( $action )
                {
                        case 'alter_add':
                        case '+':
                                $_what_to_match_in_query_string = $_parameter['value'];
                                break;
                        case 'delete':
                        case '-':
                                if ( isset( $_what_to_match_in_query_string ) )
                                {
                                        unset( $_what_to_match_in_query_string );
                                }
                                break;
                }

                if ( $_do_update_internals )
                {
                        $this->query_string_safe = $_new_query_string;

                        array_walk( $this->query_string_safe, "urldecode" );
                        array_walk( $this->query_string_safe, array( $this, "clean__excessive_separators" ) );

                        $this->query_string_safe = $this->clean__excessive_separators(
                                $this->clean__makesafe_value(
                                                        $this->query_string_safe,
                                                        array("urldecode"),
                                                        true
                                                ),
                                        "&amp;"
                                );
                        $this->query_string_real = str_replace( '&amp;' , '&' , $this->query_string_safe );
                        $this->query_string_formatted = preg_replace( "#s=([a-z0-9]){32}#", '', $this->query_string_safe );
                }

                return http_build_query( $_new_query_string );
        }


        /**
         * Parses data from/to member's ban-line DB record
         *
         * @param    mixed    Data to parse from/to
         * @return   mixed    Data parsed
         */
        public function session__handle_ban_line ( $bline )
        {
                if ( is_array( $bline ) )
                {
                        # Set ( 'timespan' 'unit' )

                        $factor = $bline['unit'] == 'd' ? 86400 : 3600;

                        $date_end = time() + ( $bline['timespan'] * $factor );

                        return time() . ':' . $date_end . ':' . $bline['timespan'] . ':' . $bline['unit'];
                }
                else
                {
                        $arr = array();

                        list( $arr['date_start'], $arr['date_end'], $arr['timespan'], $arr['unit'] ) = explode( ":", $bline );

                        return $arr;
                }
        }


        /**
         * Manually utf8 encode to a specific length
         * Based on notes found at php.net
         *
         * @param    string     Raw text
         * @param    integer    Length
         * @return   string
         *
         * @author              $Author: matt $
         * @copyright   (c) 2001 - 2010 Invision Power Services, Inc.
         * @license             http://www.invisionpower.com/community/board/license.html
         * @package             Invision Power Board
         */
        public function utf8_encode_to_specific_length ( $string , $len = 0 )
        {
                $_unicode         = '';
                $_values          = array();
                $_nOctets         = 1;
                $_unicode_length  = 0;
                $_string_length   = strlen( $string );

                for ( $i = 0 ; $i < $_string_length ; $i++ )
                {
                        $value = ord( $string[ $i ] );

                        if ( $value < 128 )
                        {
                                if ( $len and ( $_unicode_length >= $len ) )
                                {
                                        break;
                                }

                                $_unicode .= chr($value);
                                $_unicode_length++;
                        }
                        else
                        {
                                if ( count( $_values ) == 0 )
                                {
                                        $_nOctets = ( $value < 224 ) ? 2 : 3;
                                }

                                $_values[] = $value;

                                if ( $len and ( $_unicode_length + ($_nOctets * 3) ) > $len )
                                {
                                        break;
                                }

                                if ( count( $_values ) == $_nOctets )
                                {
                                        if ( $_nOctets == 3 )
                                        {
                                                $_unicode .= '%' . dechex($_values[0]) . '%' . dechex($_values[1]) . '%' . dechex($_values[2]);
                                                $_unicode_length += 9;
                                        }
                                        else
                                        {
                                                $_unicode .= '%' . dechex($_values[0]) . '%' . dechex($_values[1]);
                                                $_unicode_length += 6;
                                        }

                                        $_values  = array();
                                        $_nOctets = 1;
                                }
                        }
                }

                return $_unicode;
        }


        /**
         * Converts UFT-8 into HTML entities (&#1xxx;) for correct display in browsers
         *
         * @param     string    UTF8 Encoded string
         * @return    string    ..converted into HTML entities (similar to what a browser does with POST)
         *
         * @author              $Author: matt $
         * @copyright   (c) 2001 - 2010 Invision Power Services, Inc.
         * @license             http://www.invisionpower.com/community/board/license.html
         * @package             Invision Power Board
         */
        public function utf8__multibyte_sequence_to_html_entities ( $string )
        {
        /*
         * @see http://en.wikipedia.org/wiki/UTF-8#Description
         */

                # Four-byte chars
                $string = preg_replace(
                                "/([\360-\364])([\200-\277])([\200-\277])([\200-\277])/e",
                                "'&#' . ( ( ord('\\1') - 240 ) * 262144 + ( ord('\\2') - 128 ) * 4096 + ( ord('\\3') - 128 ) * 64 + ( ord('\\4') - 128 ) ) . ';'",
                                $string
                        );

                # Three-byte chars
                $string = preg_replace(
                                "/([\340-\357])([\200-\277])([\200-\277])/e",
                                "'&#' . ( ( ord('\\1') - 224 ) * 4096 + ( ord('\\2') - 128 ) * 64 + ( ord('\\3') - 128 ) ) . ';'",
                                $string
                        );

        # Two-byte chars
                $string = preg_replace(
                                "/([\300-\337])([\200-\277])/e",
                                "'&#' . ( ( ord('\\1') - 192 ) * 64 + ( ord('\\2') - 128 ) ) . ';'",
                                $string
                        );

        return $string;
        }


        /**
         * Convert decimal character code (e.g.: 36899 for &#36899; ) to utf-8
         *
         * @param     mixed       Character code - either numeric code or complete entity with leading &# and trailing ;
         * @return    string      Character
         *
         * @author              $Author: matt $
         * @copyright   (c) 2001 - 2009 Invision Power Services, Inc.
         * @license             http://www.invisionpower.com/community/board/license.html
         * @package             Invision Power Board
         */
        private function utf8__html_entities_to_multibyte_sequence ( $code = 0 )
        {
                if ( preg_match( '/^\&\#\d+;$/' , $code ) )
                {
                        $code = preg_replace( '/[^\d]/' , "" , $code );
                }
                elseif ( ! preg_match( '/^\d+$/' , $code ) )
                {
                        return chr(0);
                }

                $return = '';

                if ( $code < 0 )
                {
                        return chr(0);
                }
                elseif ( $code <= 0x007f )
                {
                        $return .= chr( $code );
                }
                elseif ( $code <= 0x07ff )
                {
                        $return .= chr( 0xc0 | ( $code >> 6 ) );
                        $return .= chr( 0x80 | ( $code & 0x003f ) );
                }
                elseif ( $code <= 0xffff )
                {
                        $return .= chr( 0xe0 | ( $code  >> 12 ) );
                        $return .= chr( 0x80 | ( ( $code >> 6) & 0x003f ) );
                        $return .= chr( 0x80 | ( $code  & 0x003f ) );
                }
                elseif ( $code <= 0x10ffff )
                {
                        $return .= chr( 0xf0 | ( $code  >> 18 ) );
                        $return .= chr( 0x80 | ( ( $code >> 12 ) & 0x3f ) );
                        $return .= chr( 0x80 | ( ( $code >> 6) & 0x3f ) );
                        $return .= chr( 0x80 | ( $code  &  0x3f ) );
                }
                else
                {
                        return chr(0);
                }

                return $return;
        }


        /**
         * Seems like UTF-8?
         *
         *
         * @param     string      Raw text
         * @return    boolean
         *
         * @author    hmdker at gmail dot com
         * @link      http://php.net/utf8_encode
         */
        public function is_utf8 ( $s )
        {
                /*
         * @see http://en.wikipedia.org/wiki/UTF-8#Description
         */
                $c = 0;
                $b = 0;
                $byte_nr = 0;
                $len = strlen( $s );
                for ( $i=0; $i < $len; $i++ )
                {
                        $c = ord( $str[ $i ] );
                        if ( $c > 128 )
                        {
                                if ( $c >= 254 )
                                {
                                        return false;
                                }
                                elseif ( $c >= 252 )
                                {
                                        $byte_nr = 6;                // Start of 6-byte sequence
                                }
                                elseif ( $c >= 248 )
                                {
                                        $byte_nr = 5;                // Start of 5-byte sequence
                                }
                                elseif ( $c >= 240 )
                                {
                                        $byte_nr = 4;                // Start of 4-byte sequence
                                }
                                elseif ( $c >= 224 )
                                {
                                        $byte_nr = 3;                // Start of 3-byte sequence
                                }
                                elseif ( $c >= 192)
                                {
                                        $byte_nr = 2;                // Start of 2-byte sequence
                                }
                                else
                                {
                                        return false;                // Its single-byte sequence and single-byte sequences reside in range of \x0 - \x7F (0 - 127)
                                }

                                if ( ( $i + $byte_nr ) > $len )
                                {
                                        return false;
                                }

                                # In UTF-8 encoded multi-byte string, bytes after first-one reside in range of \x80 - \xBF (128-191)
                                while ( $byte_nr > 1 )
                                {
                                        $i++;
                                        $b = ord( $str[ $i ] );
                                        if ( $b < 128 or $b > 191 )
                                        {
                                                return false;
                                        }
                                        $byte_nr--;
                                }
                        }
                }

                return true;
        }
}
