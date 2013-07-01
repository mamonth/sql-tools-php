<?php
namespace Sql;

/**
 * Builds sql condition.
 * Idea taken from ZF (Zend_db_select) && Kohana 3
 *
 * @todo Split to classes by logic ( Where, Join, etc...).
 * 
 * @author Andrew Tereshko <andrew.tereshko@gmail.com>
 */
class Condition
{
    protected $whereList = array();
    protected $joinList = array();
    protected $orderList = array();
    
    protected $limit;
    protected $offset;
    
    protected $binded = array();
    
    public function __construct( $query = null )
    {
        // here must be parsing of $query to internal 
    }

    /**
     * @param null $query
     * @return Condition
     */
    public static function factory( $query = null )
    {
        return new self( $query );
    }
    
    /**
     * Enter description here...
     *
     * @todo Rewrite condition part to more "self::where() like" way
     * 
     * @param string|array $table a table name or array( table, alias )
     * @param string $condition Join condition
     * @return Condition
     */
    public function joinLeft( $table, $condition )
    {
        if( !isset($this->joinList['left']) ) $this->joinList['left'] = array();
        
        if( is_array($table) )
        {
            reset($table);
            list( $table, $alias ) = each( $table );
        }
        else
            $alias = $table;
        
        $this->joinList['left'][ $alias ] = array( $table => $condition );
        
        return $this;
    }
    
    /**
     * Enter description here...
     *
     * @param string $where
     * @return Condition
     */
    public function where( $where )
    {
        $this->whereList[] = array( 'AND' => '( ' . $where . ' )' );
        
        return $this;
    }

    /**
     * Enter description here...
     *
     * @param string $where
     * @return Condition
     */
    public function orWhere( $where )
    {
        $this->whereList[] = array( 'OR' => '( ' . $where . ' )' );
        
        return $this;
    }

    /**
     * Enter description here...
     *
     * @param array|string $column
     * @param bool         $asc
     *
     * @return Condition
     */
    public function orderBy( $column, $asc = true )
    {
        if( !is_array( $column ) )
        {
            $clauses = array( $column => $asc ? 'asc' : 'desc' );
        }
        else
        {
            $clauses = $column;
        }

        foreach ( $clauses as $column => $direction )
        {
            if( !in_array( strtolower( $direction ), array('asc', 'desc') ) || is_int( $column ) )
            {
                $column = $direction;
                $direction = 'ASC';
            }
            
            $this->orderList[] = $column . ' ' . strtoupper( $direction );
        }
        
        return $this;
    }

    public function dropOrder()
    {
        $this->orderList = array();

        return $this;
    }
    
    /**
     * Enter description here...
     *
     * @param integer $num
     * @return Condition
     */
    public function limit( $num = null )
    {
        $this->limit = ($num === 0 || (int)$num)?(int)$num:null;
        
        return $this;
    }
    
    /**
     * Enter description here...
     *
     * @param integer $num
     * @return DAOCondition
     */
    public function offset( $num = null )
    {
        $this->offset = ($num === 0 || (int)$num)?(int)$num:null;
        
        return $this;
    }

    /**
     * Enter description here...
     *
     * @param string $param
     * @param mixed $value
     * @return Condition
     */
    public function bindValue( $param, $value )
    {
        $this->binded[ $param ] = $value;
        
        return $this;
    }
    
    /**
     * Enter description here...
     *
     * @return string
     */
    public function build()
    {
        $query = array();
        
        if( sizeof( $this->joinList ) )
        {
            $join = '';
            foreach( $this->joinList as $type => $joins )
            {
                foreach( $joins as $alias => $table )
                {
                    list( $table, $condition ) = each($table);
                    
                    $join .= strtoupper( $type ) . ' JOIN ' . $table . ' AS ' . $alias . ' ON ' . $condition;
                }
            }
            
            if( $join ) $query[] = $join;
        }
        
        if( sizeof( $this->whereList ) )
        {
            $where = '';
            
            foreach ( $this->whereList as $row )
                 $where .= ( $where? ' ' . key($row) . ' ' : '' ) . current($row);
            
            if( $where ) $query[] = 'WHERE ' . $where;
        }
        
        if( sizeof( $this->orderList ) )
            $query[] = 'ORDER BY ' . implode( ', ', $this->orderList );
        
        if( null !== $this->offset && null === $this->limit )
            $this->limit = 0;
            
        if( null !== $this->limit )
            $query[] = 'LIMIT ' . (int)$this->limit;
        
        if( null !== $this->offset )
            $query[] = 'OFFSET ' . (int)$this->offset;

        $queryString = implode( ' ', $query );
                   
        foreach( $this->binded as $var => $value )
        {
            if( is_numeric( $value ) )
                $queryString = str_replace( $var, $value, $queryString );
            elseif( null === $value )
                $queryString = str_replace( $var, 'NULL', $queryString );  
            elseif( is_array( $value ) )
                continue;//throw new Exception('Arrays is not supported yet !');
            else    
                $queryString = str_replace( $var, "'" . preg_replace( "/[^\\\]'/i", "\'", $value ) . "'", $queryString );  
        }
            
        return $queryString;
    }
    
    public function __toString()
    {
        return $this->build();
    }
}
