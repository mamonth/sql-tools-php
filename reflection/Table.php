<?php
namespace Sql\Reflection;

/**
 * Sql table reverse-engineering class.
 * 
 * @property string $name
 * @property string $sql
 * @property \Sql\Reflection\Column[] $columns
 * @property \Sql\Reflection\Index[] $indexes
 *
 * @uses \Sql\Reflection\Column
 * @uses \Sql\Reflection\Index
 * 
 * @author Andrew Tereshko <andrew.tereshko@gmail.com>
 */
class Table
{
    /**
     * Table name
     * 
     * @var string 
     */
    protected $name;

    /**
     * Table namespace (schema)
     *
     * @var string
     */
    protected $schema;

    /**
     * Sourse SQL code
     * 
     * @var string 
     */
    protected $sql;
    
    /**
     * Array of table column reflection objects
     * 
     * @var array 
     */
    protected $columns = array();
    
    /**
     * Array of table index reflection objects
     * 
     * @var array 
     */
    protected $indexes = array();
    
    /**
     * Creates Table reflection object from sql string
     * 
     * @param string $sql string with one or many CREATE TABLE definition.
     * @return \Sql\Reflection\Table | null
     */
    public static function sqlFactory( $sql )
    {        
        $commands = \Sql\Reflection\Tools::splitByCommands( $sql );
        
        $tableReflection = new self;
        
        foreach( $commands as $command )
        {
            $result = $tableReflection->parseCreateTable( $command );
            
            if( true === $result ) break;
        }
        
        return $tableReflection;
    }
    
    /**
     * Creates Table reflection object from pdo connection.
     * 
     * @return \Sql\Reflection\Table | array[ \Sql\Reflection\Table ] | null
     */
    public static function pdoFactory( \PDO $pdo, $table = null )
    {
        $tableReflection = new self();
        
        return $tableReflection;
    }
    
    /**
     * Parse CREATE TABLE statement
     *
     * Expected format is:
     * - CREATE TABLE table_name [IF NOT EXISTS] (column_name column_definition[,...])
     *
     * - IF NOT EXISTS is considered non-portable, though.
     *
     * column_definition format is:
     * - @see method parse_column_definition
     *
     * We do not even attempt to parse anything beyond the closing block of column definitions.
     * That means no support for subqueries and anything like that. Maybe in the future.
     *
     * This method fills the $this->sql_data associative array with the following information:
     *
     * 	array(
     * 		'table_name'				// string
     * 		'primary_keys'				// @see method parse_column_definition
     * 		'indexes'					// @see method parse_column_definition
     * 		'columns'					// @see method parse_column_definition
     * 	);
     *

     * @param string SQL statement.
     * @return bool FALSE if any error was found.
     */
    protected function parseCreateTable( $sqlStatement )
    {
        // Obtain the table name and check its correctness
        $this->name = preg_replace( '#^CREATE TABLE (\w[0-9_\-A-z]+\.?\w[0-9_\-A-z]+).*#i', '$1', $sqlStatement );

        // Check presence of and extract column definitions list into a string (and other attributes, if any)
        if ( !preg_match( '#^CREATE TABLE ' . $this->name . '( (\w+ \w+ \w+)){0,1}\((.*)\)( (.+)){0,1}$#i', $sqlStatement, $match ) || empty( $match[3] ) )
        {
            return false;
        }

        // Search for table namespace (schema)
        if( strstr( $this->name, '.') )
        {
            list( $this->schema, $this->name ) = explode( '.', $this->name );
        }

        // Split into an array of elements for each single column definition.
        $columnDefinitions = \Sql\Reflection\Tools::splitElementsList( $match[3] );

        // Time to parse each column\index definition.
        while( list( , $columnSql ) = each( $columnDefinitions ) )
        {
            $index = \Sql\Reflection\Index::sqlFactory( $columnSql );
            
            if( $index )
            {
                $this->indexes[ $index->name ] = $index;
                
                break;
            }
            
            $column = \Sql\Reflection\Column::sqlFactory( $columnSql );  
            
            if( $column )
            {
                $this->columns[ $column->name ] = $column;
            }
        }

        unset( $columnDefinitions, $columnSql );

        // Verify consistency of primary keys and indexes -vs- columns...
//        
//        // Primary keys should exist as columns and have the not null attribute
//        for ( $i = 0; $i < count( $this->sqlData['primary_keys'] ); $i++ )
//        {
//            $keyData = &$this->sqlData['primary_keys'][$i];
//
//            // Mark column as being primary key
//            $this->sqlData['columns'][$keyData['name']]['primary_key'] = true;
//        }
//
//        // Index keys should exist as columns, also checking index length consistency.
//        foreach ( $this->sqlData['indexes'] as $indexName => $indexData )
//        {
//            for ( $i = 0; $i < count( $indexData['keys'] ); $i++ )
//            {
//                $keyData = &$indexData['keys'][$i];
//                $datatypeName = $this->sqlData['columns'][$keyData['name']]['datatype_name'];
//                // Mark column as being indexed
//                $this->sqlData['columns'][$keyData['name']]['indexed'] = true;
//            }
//        }
        return true;
    }
    
    public function __get( $property )
    {
        return $this->{$property};
    }
    
    private function __construct(){}
}
