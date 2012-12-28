<?php
namespace Sql;

/**
 * 
 * @author unknown (taken from Zen CMF)
 */
class SqlParser
{

    private $tokensCount = 0;
    private $constantTokens = array( );
    private $sqlData;
    private $dataDictionary;
    private $maxIdentifierLength = 30;


    const REG_SQL_PARSE_STATEMENT = '/,(?=(?:[^\(]*\)[^\(]*\))*(?![^\(]*\)))(?=(?:[^\"]*\"[^\"]*\")*(?![^\"]*\"))(?=(?:[^\']*\'[^\']*\')*(?![^\']*\'))/';


    public function __construct( $sqlData )
    {
        $this->sqlData = $this->splitString( $sqlData );
    }

    public function getCreateStatement( $num = 0 )
    {
        // Process each SQL statement.
        for ( $i = 0; $i < count( $this->sqlData ); $i++ )
        {
            list( $command, $option ) = explode( ' ', $this->sqlData[$i] );
            
            if ( strtoupper( $command == 'CREATE' ) && $option == 'TABLE' )
            {
                return $this->sqlData[$i];
            }
        }
    }

    public function getCreateData( $num = 0 )
    {
        $statement = $this->getCreateStatement( $num );

        //$lines = preg_split( self :: REG_SQL_PARSE_STATEMENT, $statement );
        $this->parseCreateTable( $statement );
        return $this->sqlData;
    }

    /**
     * Parse Table Column or Index Definitions.
     *
     * Expected formats are:
     * - PRIMARY KEY (index_key_definition[,...])
     * - {KEY|INDEX} [index_name] (index_key_definition[,...])
     * - UNIQUE [INDEX] [index_name] (index_key_definition[,...])
     * - column_name data_type [attribute [ ...]]
     *
     * index_key_definition format is:
     * - @see method parse_index_keys
     *
     * Note: CONSTRAINT, FOREIGN and other attributes are not supported.
     * Note: FULLTEXT|SPATIAL indexes are not supported either, for now.
     *
     * This method fills the $this->sql_data associative array with the following information:
     *
     * - For Primary Keys:
     * 		$sql_data['primary_keys']			// Index Keys information as returned by the parse_index_keys method.
     *
     * - For Indexes:
     * 		$sql_data['indexes'] = array(		// An associative array where each element contains information for a single index.
     * 			[$index_name] = array(			// For each index (note the array key is the Index Name):
     * 				'name'						// string Index Name.
     * 				'unique'					// bool TRUE if this is an Unique Index.
     * 				'keys'						// Index Keys information as returned by the parse_index_keys method.
     * 			),
     * 		);
     *
     * - For Column Definitions:
     * 		$sql_data['columns'] = array(		// An associative array where each element contains information for a single column.
     * 			[$column_name] = array(			// For each column (note the array key is the Column Name):
     * 				'name'						// string Column Name (required)
     * 				'datatype'					// string Data Type, may contain (length[,decimals]) (required)
     * 				'datatype_name'				// string Only the Data Type itself here (@see property $valid_datatypes)
     * 				'datatype_argc'				// integer Number of elements used to specify (length[,decimals])
     * 				'datatype_argv'				// array Each element used to specify (length[,decimals])
     * 				'constant_type'				// string A single character indicator of the constant type (@see property $valid_datatypes)
     * 				'unsigned'					// bool and TRUE if specified (optional, for numeric types only)
     * 				'zerofill'					// bool and TRUE if specified (optional, for numeric types only)
     * 				'binary'					// bool and TRUE if specified (optional, for char and varchar only)
     * 				'null'						// string '', 'NULL' or 'NOT NULL' (optional, not null is required for primary keys)
     * 				'default'					// string filled only if specified (optional)
     * 				'auto_increment'			// bool and TRUE if specified (optional, if used column must be indexed)
     * 			),
     * 		);
     *
     * @access private
     * @return bool FALSE if any error was found.
     */
    function parseColumnDefinition( $columnDefinition )
    {
        // Make sure arrays exist
        if ( !isset( $this->sqlData['primary_keys'] ) )
        {
            $this->sqlData['primary_keys'] = array( );
        }
        if ( !isset( $this->sqlData['indexes'] ) )
        {
            $this->sqlData['indexes'] = array( );
        }
        if ( !isset( $this->sqlData['columns'] ) )
        {
            $this->sqlData['columns'] = array( );
        }



        //
        // Oh! we have (or we should) a column definition here, let's see. hmm...
        //

    }


    /**
     * Restore a single String Constant.
     *
     * @access private
     * @param string Token to lookup.
     * @return string Constant if token was found, otherwise return input as-is.
     */
    function restoreSqlConstant( $token )
    {
        return isset( $this->constantTokens['#' . $token . '#'] ) ? $this->constantTokens['#' . $token . '#'] : $token;
    }



}
