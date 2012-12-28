<?php
namespace Sql\Reflection;

/**
 * SQL column reverse-engineering class.
 *
 * @property string $name
 * @property string $datatype
 * @property int $dataLength
 * @property mixed $default
 * @property bool $null
 * @property string $onDelete
 * @property string $onUpdate
 * @property bool $zerofill
 * @property bool $unsigned
 * @property bool $binary
 * @property string $constraint
 * @property string $foreignKey
 * @property string $tableReference
 * @property string $columnReference
 * 
 * @author Andrew Tereshko <andrew.tereshko@gmail.com>
 */
class Column
{
    protected $name;
    
    protected $datatype;
    
    protected $dataLength;

    protected $default;
    
    protected $null = true;
    
    protected $onDelete;
    
    protected $onUpdate;
    
    protected $zerofill = false;
    
    protected $unsigned = false;
    
    protected $binary = false;
    
    protected $constraint;
    
    protected $foreignKey;
    
    protected $tableReference;
    
    protected $columnReference;
    
    /**
     *
     * @param type $sql
     * @return \Sql\Reflection\Column
     */
    public static function sqlFactory( $sql )
    {
        // Sometimes, small changes make life easier
        $columnAttributes = preg_replace( '#NOT NULL#i', 'NOT_NULL', $sql );
        $columnAttributes = preg_replace( '#DOUBLE PRECISION#i', 'DOUBLE', $columnAttributes );
        $columnAttributes = preg_replace( '#ON DELETE#i', 'ONDELETE', $columnAttributes );
        $columnAttributes = preg_replace( '#ON UPDATE#i', 'ONUPDATE', $columnAttributes );
        $columnAttributes = preg_replace( '#SET DEFAULT#i', 'SETDEFAULT', $columnAttributes );

        // Transform column definition into an array of words! ...where
        // each word is a single column attribute, except DEFAULT value
        $columnAttributes = explode( ' ', $columnAttributes );

        $columnReflection = new self;
        
        // Obtain/check column name
        $columnReflection->name = array_shift( $columnAttributes );

        // and datatype
        $columnReflection->datatype = strtoupper( array_shift( $columnAttributes ) );
       
        if( !$columnReflection->name || !$columnReflection->datatype )
        {
            return null;
        }
        
        $columnReflection->sql = $sql;

        // string to upper the rest of attributes to make it easier/faster to digest
        $upColumnAttributes = array_map( 'strtoupper', $columnAttributes );

        // if column does have external references
        if ( ($i = array_search( 'REFERENCES', $upColumnAttributes )) !== false )
        {
            $columnAttribute = $columnAttributes[$i + 1];
            list( $table, $attr ) = explode( '(', $columnAttribute, 2 );
            $attr = str_replace( ')', '', $attr );

            // If necessary, replace token with saved string constant
            $columnReflection->tableReference = $table;
            $columnReflection->columnReference = explode( ',', $attr );
            
            unset( $columnAttributes[$i + 1], $columnAttributes[$i] );
        }

        if ( ($i = array_search( 'CONSTRAINT', $upColumnAttributes )) !== false )
        {
            // If necessary, replace token with saved string constant
            $columnReflection->constraint = \Sql\Reflection\Tools::restoreSqlConstant( $columnAttributes[$i + 1] );
            unset( $columnAttributes[$i + 1], $columnAttributes[$i] );
        }

        if ( ($i = array_search( 'FOREIGN KEY', $upColumnAttributes )) !== false )
        {
            // If necessary, replace token with saved string constant
            $columnReflection->foreignKey = \Sql\Reflection\Tools::restoreSqlConstant( $columnAttributes[$i + 1] );
            unset( $columnAttributes[$i + 1], $columnAttributes[$i] );
        }

        if ( ($i = array_search( 'ONDELETE', $upColumnAttributes )) !== false )
        {
            // If necessary, replace token with saved string constant
            $columnReflection->onDelete = \Sql\Reflection\Tools::restoreSqlConstant( $columnAttributes[$i + 1] );
            unset( $columnAttributes[$i + 1], $columnAttributes[$i] );
        }

        if ( ($i = array_search( 'ONUPDATE', $upColumnAttributes )) !== false )
        {
            // If necessary, replace token with saved string constant
            $columnReflection->onUpdate = \Sql\Reflection\Tools::restoreSqlConstant( $columnAttributes[$i + 1] );
            unset( $columnAttributes[$i + 1], $columnAttributes[$i] );
        }

        // string to upper the rest of attributes to make it easier/faster to digest
        $columnAttributes = array_map( 'strtoupper', $columnAttributes );


        // Parse default value (apart from NOT NULL and DOUBLE PRECISION "fixed" before, it is the only one that needs 2 words)
        if ( ($i = array_search( 'DEFAULT', $columnAttributes )) !== false )
        {
            // If necessary, replace token with saved string constant
            $columnReflection->default = \Sql\Reflection\Tools::restoreSqlConstant( $columnAttributes[$i + 1] );
            
            if( $columnReflection->default == 'NULL' ) $columnReflection->default = null;
            
            unset( $columnAttributes[$i + 1], $columnAttributes[$i] );
        }

        // Parse column attributes
        $attributeWords = array( 'AUTO_INCREMENT', 'BINARY', 'NOT_NULL', 'NULL', 'UNSIGNED', 'ZEROFILL' );
        for ( $i = 0; $i < count( $attributeWords ); $i++ )
        {
            $attribute = &$attributeWords[$i];
            if ( ($j = array_search( $attribute, $columnAttributes )) !== false )
            {
                if ( $attribute == 'NULL' || $attribute == 'NOT_NULL' )
                {
                    $columnReflection->null = $attribute == 'NULL' ? true : false;
                }
                elseif( $attribute == 'UNSIGNED' )
                {
                    $columnReflection->unsigned = true;
                }
                elseif( $attribute == 'ZEROFILL' )
                {
                    $columnReflection->zerofill = true;
                }
                elseif( $attribute == 'BINARY' )
                {
                    $columnReflection->binary = true;
                }
                else
                {
                    
                }
                unset( $columnAttributes[$j] );
            }
        }

        // Trying to understand the datatype...
        $datatypeArgv = explode( '(', str_replace( ',', '(', rtrim( $columnReflection->datatype, ')' ) ) );
        $columnReflection->datatype = array_shift( $datatypeArgv );
        $datatypeArgc = count( $datatypeArgv );
        
        if( $datatypeArgc == 1 && is_numeric( $datatypeArgv[0] ) )
        {
            $columnReflection->dataLength = (int)$datatypeArgv[0];
        }

        // Deal with data type aliases 
        // @TODO realise
//        if ( isset( $this->datatypeAliases[$datatypeName] ) )
//        {
//            $datatypeName = $this->datatypeAliases[$datatypeName];
//            $columnReflection->datatype = $datatypeName . ( $datatypeArgc > 0 ? '(' . implode( ',', $datatypeArgv ) . ')' : '' );
//        }

        // Some people tend to use string constants for numeric types, but that may lead to problems on
        // some non-MySQL servers, we here try to fix "wrong" default values for numeric types.
        if ( isset( $columnReflection->default ) && preg_match( '#[\'"]#', $columnReflection->default  ) && @strstr( 'IFD', self::$validDatatypes[ $columnReflection->datatype ]['C'] ) )
        {
            $columnReflection->default  = trim( substr( $columnReflection->default , 1, -1 ) );
            if ( strlen( $columnReflection->default  ) <= 0 )
            {
                $columnReflection->default = 0;
            }
        }

        // Check/normalize values used in default clausule -vs- specified data type
        if ( null !== $columnReflection->default )
        {

            switch ( @self::$validDatatypes[ $columnReflection->datatype ]['C'] )
            {
                case 'B': // Check/normalize boolean constants
                    // Make sure booleans are stored as 1 or 0
                    if ( preg_match( '#^(true|false)$#i', $columnReflection->default ) )
                    {
                        $columnReflection->default = ( strtolower( $columnReflection->default ) == 'true' ? 1 : 0 );
                    }
                    elseif ( $columnReflection->default !== 0  )
                    {
                        $columnReflection->default = 1;
                    }
                    
                    break;

                case 'I': // Check/normalize Integer constants
                    if ( $columnReflection->datatype == 'BIGINT' || ( $columnReflection->datatype == 'INTEGER' && $columnReflection->unsigned ) )
                    {
                        // Get the big integer as a string, trying to ensure it is correctly handled by PHP
                        $columnReflection->default = preg_replace( '#^([^\.]*).*$#', '$1', (float) $columnReflection->default );
                    }
                    else
                    {
                        $columnReflection->default = (int) $columnReflection->default;
                    }
                    break;

                case 'D': // Check/normalize Decimal constants
                    if ( !preg_match( '#^[\+\-]{0,1}(([0-9]+\.[0-9]*)|([0-9]*\.[0-9]+)|([0-9]+))$#', $columnReflection->default ) )
                    {
                        $columnReflection->default = (float) $columnReflection->default;
                    }
                    break;

                case 'F': // Check/normalize Float/Double constants
                    $columnReflection->default = (float) $columnReflection->default;
                    break;

                case 'C': // Check/normalize Character constants
                case 'Y': // Check/normalize binarY constants
                    // If string constant is double quoted
                    if ( $columnReflection->default{0} == '"' )
                    {
                        // convert into a single quoted string constant
                        $columnReflection->default = "'" . preg_replace( '#([\\\\"]"|\')#', '\'\'', substr( $columnReflection->default, 1, -1 ) ) . "'";
                    }
                    break;
            }
        }


        // Check for MySQL extensions
        if ( $columnReflection->zerofill && !$columnReflection->unsigned )
        {
                // This is done by MySQL anyway, we activate the UNSIGNED flag here
                // to help builder to be a bit more consistent.
                $columnReflection->unsigned = true;
        }

//        // Save column name and type in data dictionary.
//        if ( !isset( $this->dataDictionary[$columnName] ) )
//        {
//            $this->dataDictionary[$columnName] = array( );
//        }
//        if ( !isset( $this->dataDictionary[$columnName][$columnData['datatype']] ) )
//        {
//            $this->dataDictionary[$columnName][$columnData['datatype']] = array( );
//        }
//        $this->dataDictionary[$columnName][$columnData['datatype']][] = $this->sqlData['table_name'];
//
//        // We got it here, fwiw, save extra datatype information
//        $columnData['datatype_name'] = $datatypeName;
//        $columnData['datatype_argc'] = $datatypeArgc;
//        $columnData['datatype_argv'] = $datatypeArgv;
//
//        if ( isset( $this->validDatatypes[$datatypeName] ) )
//            $columnData['constant_type'] = $this->validDatatypes[$datatypeName]['C'];
//
//        // Hopefully good enough! Save all attributes for later processing and quit.
//        $this->sqlData['columns'][$columnName] = $columnData;
//        return true;
        
        return $columnReflection;
    }
    
    /**
     * Valid (or Supported) Data Types.
     *
     * Each datatype defines the following attributes:
     * 'A' => 1 allow the auto_increment attribute, 0 disallowed.
     * 'B' => 1 allow the binary attribute, 0 disallowed.
     * 'C' => Constant type, used to check/transform constants ('B'oolean, 'I'nteger, 'F'loat, 'D'ecimal, 'C'har and binar'Y').
     * 'K' => 1 allow to specify length in index keys, 2 required, 0 disallowed.
     * 'L' => Number of elements allowed to specify column size, display width or (length,decimals).
     *
     * Second and third elements of the 'L' array represent the maximum values
     * to specify display widths (DW) for integer types, based on their precision:
     *
     * TYPE                 BYTES       MINIMUM UNSIGNED           DW     MAXIMUM UNSIGNED             DW       MAXIMUM SIGNED                  DW
     * ---------                -----           ---------------------                     --        ---------------------                       --          ---------------------                        --
     * TINYINT               1            -128                                   4        +127                                    4          +255                                     4
     * SMALLINT            2            -32768                               6        +32767                                6          +65535                                 6
     * MEDIUMINT         3            -8388608                           8        +8388607                            8          +16777215                           9
     * INTEGER             4            -2147483648                     11      +2147483647                      11        +4294967295                       11
     * BIGINT                8            -9223372036854775808   20      +9223372036854775807    20        +18446744073709551615   21
     *
     * 'U' => 1 allow the unsigned attribute, 0 disallowed.
     * 'Z' => 1 allow the zerofill attribute, 0 disallowed.
     *
     * References:
     * http://dev.mysql.com/doc/mysql/en/numeric-types.html
     * http://dev.mysql.com/doc/mysql/en/char.html
     * http://dev.mysql.com/doc/mysql/en/binary-varbinary.html
     * http://dev.mysql.com/doc/mysql/en/blob.html
     *
     * @access private
     */
    private static $validDatatypes = array(
        'BOOLEAN' => array( 'A' => 0, 'B' => 0, 'C' => 'B', 'K' => 0, 'L' => array( 0, 0, 0 ), 'U' => 0, 'Z' => 0 ),
        'TINYINT' => array( 'A' => 1, 'B' => 0, 'C' => 'I', 'K' => 0, 'L' => array( 1, 4, 4 ), 'U' => 1, 'Z' => 1 ),
        'SMALLINT' => array( 'A' => 1, 'B' => 0, 'C' => 'I', 'K' => 0, 'L' => array( 1, 6, 6 ), 'U' => 1, 'Z' => 1 ),
        'MEDIUMINT' => array( 'A' => 1, 'B' => 0, 'C' => 'I', 'K' => 0, 'L' => array( 1, 8, 9 ), 'U' => 1, 'Z' => 1 ),
        'INTEGER' => array( 'A' => 1, 'B' => 0, 'C' => 'I', 'K' => 0, 'L' => array( 1, 11, 11 ), 'U' => 1, 'Z' => 1 ),
        'INT' => array( 'A' => 1, 'B' => 0, 'C' => 'I', 'K' => 0, 'L' => array( 1, 11, 11 ), 'U' => 1, 'Z' => 1 ),
        'SERIAL' => array( 'A' => 1, 'B' => 0, 'C' => 'I', 'K' => 0, 'L' => array( 1, 11, 11 ), 'U' => 1, 'Z' => 1 ),
        'BIGINT' => array( 'A' => 1, 'B' => 0, 'C' => 'I', 'K' => 0, 'L' => array( 1, 20, 21 ), 'U' => 1, 'Z' => 1 ),
        'FLOAT' => array( 'A' => 0, 'B' => 0, 'C' => 'F', 'K' => 0, 'L' => array( 2, 0, 0 ), 'U' => 1, 'Z' => 1 ),
        'DOUBLE PRECISION' => array( 'A' => 0, 'B' => 0, 'C' => 'F', 'K' => 0, 'L' => array( 2, 0, 0 ), 'U' => 1, 'Z' => 1 ),
        'DECIMAL' => array( 'A' => 0, 'B' => 0, 'C' => 'D', 'K' => 0, 'L' => array( 2, 0, 0 ), 'U' => 1, 'Z' => 1 ),
        'NUMERIC' => array( 'A' => 0, 'B' => 0, 'C' => 'D', 'K' => 0, 'L' => array( 2, 0, 0 ), 'U' => 1, 'Z' => 1 ),
        'CHAR' => array( 'A' => 0, 'B' => 1, 'C' => 'C', 'K' => 1, 'L' => array( 1, 0, 0 ), 'U' => 0, 'Z' => 0 ),
        'VARCHAR' => array( 'A' => 0, 'B' => 1, 'C' => 'C', 'K' => 1, 'L' => array( 1, 0, 0 ), 'U' => 0, 'Z' => 0 ),
        'BINARY' => array( 'A' => 0, 'B' => 0, 'C' => 'Y', 'K' => 1, 'L' => array( 1, 0, 0 ), 'U' => 0, 'Z' => 0 ),
        'VARBINARY' => array( 'A' => 0, 'B' => 0, 'C' => 'Y', 'K' => 1, 'L' => array( 1, 0, 0 ), 'U' => 0, 'Z' => 0 ),
        'TINYTEXT' => array( 'A' => 0, 'B' => 0, 'C' => 'C', 'K' => 2, 'L' => array( 0, 0, 0 ), 'U' => 0, 'Z' => 0 ),
        'TEXT' => array( 'A' => 0, 'B' => 0, 'C' => 'C', 'K' => 2, 'L' => array( 0, 0, 0 ), 'U' => 0, 'Z' => 0 ),
        'MEDIUMTEXT' => array( 'A' => 0, 'B' => 0, 'C' => 'C', 'K' => 2, 'L' => array( 0, 0, 0 ), 'U' => 0, 'Z' => 0 ),
        'LONGTEXT' => array( 'A' => 0, 'B' => 0, 'C' => 'C', 'K' => 2, 'L' => array( 0, 0, 0 ), 'U' => 0, 'Z' => 0 ),
        'TINYBLOB' => array( 'A' => 0, 'B' => 0, 'C' => 'Y', 'K' => 2, 'L' => array( 0, 0, 0 ), 'U' => 0, 'Z' => 0 ),
        'BLOB' => array( 'A' => 0, 'B' => 0, 'C' => 'Y', 'K' => 2, 'L' => array( 0, 0, 0 ), 'U' => 0, 'Z' => 0 ),
        'MEDIUMBLOB' => array( 'A' => 0, 'B' => 0, 'C' => 'Y', 'K' => 2, 'L' => array( 0, 0, 0 ), 'U' => 0, 'Z' => 0 ),
        'LONGBLOB' => array( 'A' => 0, 'B' => 0, 'C' => 'Y', 'K' => 2, 'L' => array( 0, 0, 0 ), 'U' => 0, 'Z' => 0 ),
        'ENUM' => array( 'A' => 0, 'B' => 0, 'C' => 'I', 'K' => 0, 'L' => array( 0, 0, 0 ), 'U' => 0, 'Z' => 1 ),
        'DATETIME' => array( 'A' => 0, 'B' => 0, 'C' => 'I', 'K' => 0, 'L' => array( 0, 0, 0 ), 'U' => 0, 'Z' => 1 ),
        'DATE' => array( 'A' => 0, 'B' => 0, 'C' => 'I', 'K' => 0, 'L' => array( 0, 0, 0 ), 'U' => 0, 'Z' => 1 ),
        'TIMESTAMP' => array( 'A' => 0, 'B' => 0, 'C' => 'I', 'K' => 0, 'L' => array( 0, 0, 0 ), 'U' => 0, 'Z' => 1 )
    );
    
    public function __get( $property )
    {
        return $this->{$property};
    }
    
    private function __construct(){}
}
