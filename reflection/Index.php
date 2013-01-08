<?php

namespace Sql\Reflection;

/**
 * Description of Index
 *
 * @author Andrew Tereshko <andrew.tereshko@gmail.com>
 */
class Index
{
    const T_INDEX = 0;
    const T_UNIQUE = 1;
    const T_PRIMARY = 2;

    private $name;
    
    private $type;
    
    private $keys;
    
    public function getKeyNames()
    {
        
    }

    public static function sqlFactory( $sql )
    {
        // Check if this is an index definition
        // @TODO move FOREIGN KEY and EXCLUDE to separate entity
        if ( !preg_match( '#^(PRIMARY KEY|KEY|FOREIGN KEY|UNIQUE INDEX|INDEX|UNIQUE|EXCLUDE)[^\w]#i', $sql, $match ) )
        {
            return false;
        }
        
        // Extract index information
        if ( !preg_match( '#^((' . $match[1] . ')[^(]*)\((.*)\)$#i', $sql, $match ) )
        {
            throw new \Sql\Reflection\Exception( 'Invalid index definition "' . $sql . '"' );
            //return false;
        }
        
        $indexReflection = new self;
        
        $indexReflection->name = trim( str_replace( $match[2], '', $match[1] ) );
        
        $indexReflection->type = stristr( $match[2], 'PRIMARY' ) ? self::T_PRIMARY : ( stristr( $match[2], 'UNIQUE' ) ? self::T_UNIQUE : self::T_INDEX );

        // Check/obtain index keys
        $indexReflection->keys = self::parseIndexKeys( trim( $match[3] ), ($indexReflection->type != self::T_PRIMARY ? true : false ) );
        
        if ( !$indexReflection->keys )
        {
            throw new \Sql\Reflection\Exception( 'Could not parse keys "' . $match[3] . '" for index definition "' . $sql . '"' );
            return false;
        }

        // Process for Indexes, first make sure we have an index name
        if ( empty( $indexReflection->name ) )
        {
            $keyNames = array();
            
            foreach( $indexReflection->keys as $key )
            {
                $keyNames[] = $key['name'];
            }
            
            $indexReflection->name = implode( '_', $keyNames );
            
            unset( $keyNames );
        }
        
        unset( $match, $sql );

        return $indexReflection;
    }
    
   /**
     * Parse List of Index Keys.
     *
     * Expected format is:
     * - index_key_definition[,...]
     *
     * index_key_definition format is:
     * - column_name[(length[,...])] [ASC|DESC]
     *
     * Note (length) is only accepted if the $key_length_allowed argument is true.
     * Within this context we can't check its correctness, though.
     *
     * This method returns information in the following format:
     *
     * 		array(				// An array where each element contains information for a single index key.
     * 			array(			// An associative array where each element is an attribute.
     * 				'name'		// string Column Name.
     * 				'length'	// integer Prefix Length for Index (0 if not specified).
     * 				'order'		// string Key Order as DESC or ASC (default).
     * 			),
     * 		);
     *
     * @param string List of index keys.
     * @param bool TRUE if key lengths are allowed.
     * @return array An element for each key.
     */
    private static function parseIndexKeys( $indexAttributes, $keyLengthAllowed )
    {
        $indexAttributes = \Sql\Reflection\Tools::splitElementsList( $indexAttributes );
        $indexKeys = array( );

        for ( $i = 0; $i < count( $indexAttributes ); $i++ )
        {
            $keyItems = explode( ' ', $indexAttributes[$i] );
            $keyItemsCount = count( $keyItems );
            if ( $keyItemsCount == 2 )
            {
                $keyItems[1] = strtoupper( $keyItems[1] );
            }
            else
            {
                $keyItems[1] = 'ASC';
            }

            $keyData = array( 'name' => $keyItems[0], 'length' => false, 'order' => $keyItems[1] );

            if ( $keyLengthAllowed && strlen( $keyData['name'] ) > 2 && ($pos = strpos( $keyData['name'], '(' )) !== false && $keyData['name']{strlen( $keyData['name'] ) - 1} == ')' )
            {
                $keyParts = explode( '(', strReplace( ')', '(', $keyData['name'] ) );
                $keyParts[1] = intval( $keyParts[1] );
                if ( $keyParts[1] > 0 )
                {
                    $keyData['name'] = $keyParts[0];
                    $keyData['length'] = $keyParts[1];
                }
            }

            $indexKeys[] = $keyData;
        }
        return $indexKeys;
    }
    
    public function __get( $property )
    {
        return $this->{$property};
    }

    private function __construct()
    {
        
    }

}
