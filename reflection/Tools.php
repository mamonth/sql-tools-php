<?php
namespace Sql\Reflection;

/**
 * Various static methods for SQL Reflection
 *
 * @author Andrew Tereshko <andrew.tereshko@gmail.com>
 */
class Tools
{
    private static $constCount = 0;
    
    private static $constTokens = array();
    
    public static function splitByCommands( $sqlString )
    {
        // Split string into an array of lines (dealing with LF, CRLF and CR).
        $tmpArray = preg_split( "/\r?\n|\r/", $sqlString, -1, PREG_SPLIT_NO_EMPTY );
        $sqlString = '';
        
        // Clean up the input
        $sqlArray = array( );
        for ( $i = 0; $i < count( $tmpArray ); $i++ )
        {
            // Replace string constants with tokens and remove leading/trailing whitespaces
            $line = self::extractSqlConstants( $tmpArray[$i] );

            // Remove empty lines
            if ( !empty( $line ) )
            {
                $sqlArray[] = $line;
            }
        }
        unset( $tmpArray, $line );

        // Remove SQL-style comments
        $sqlArray = array_map( function( $v ){ return preg_replace( '#^\s*--.*/#', '', $v ); }, $sqlArray );
        
        // Convert array into a long string again.
        $sqlString = implode( ' ', $sqlArray );

        // Remove C-style comments, introduced by /* and ended with */
        $sqlString = preg_replace( '#/\*.*?\*/#', '', $sqlString );
       
        // Normalize SQL syntax
        $sqlString = self::normalizeSyntax( $sqlString );

        // Check for semicolon presence in last statement
        if ( $sqlString{ strlen( $sqlString ) - 1 } == ';' )
        {
            // Strip out latest semicolon to prevent explode from creating an empty element at end of array.
            $sqlString = substr( $sqlString, 0, -1 );
        }

        // Split string into an array where each element is a single SQL statement.
        $sqlArray = explode( ';', $sqlString );

        return $sqlArray;
    }
    
    public static function extractSqlConstants( $input )
    {
        $inputLength = mb_strlen( $input );
        $output = $quote = $constant = '';

        for ( $i = 0; $i < $inputLength; $i++ )
        {
            //$char = $input{$i};
            $char = mb_substr( $input, $i, 1 );

            // If we were inside a string constant, $quote would contain the delimiter.
            if ( empty( $quote ) )
            {
                // We aren't part of any string constant, let's see if we can find one.
                if ( $char == "'" || $char == '"' )
                {
                    $quote = $char;
                }
                else
                {
                    // If this is a simple SQL comment mark, ignore the rest of the input
                    $next = ( ($i + 1) >= $inputLength ? '' : $input{$i + 1} );
                    if ( $char == '#' || ( $char == '-' && $next == '-' ) )
                    {
                        break;
                    }
                    $output .= $char;
                }
            }
            else
            {
                // Is this is an escape character or a trailing delimiter?
                if ( $char == '\\' || $char == $quote )
                {
                    // Is this is an escape character or is it a delimiter escaping itself?
                    $next = ( ($i + 1) >= $inputLength ? '' : $input{$i + 1} );
                    if ( $char == '\\' && ( $char == $quote && $char == $next ) )
                    {
                        // If so, current char and next one are both part of the string constant.
                        $constant .= $char . $next;
                        $i++;
                    }
                    else
                    {
                        // This is the trailing delimiter, save it and replace the constant with a unique token.
                        $token = '___' . self::$constCount++ . '___';
                        $output .= $token;
                        self::$constTokens['#' . $token . '#'] = $quote . $constant . $quote;
                        $quote = $constant = '';
                    }
                }
                else
                {
                    // This char is part the current string constant.
                    $constant .= $char;
                }
            }
        }

        // If $quote is not empty, we found a non-closed string constant!
        if ( !empty( $quote ) )
        {
            throw new \Sql\Reflection\Exception( 'SQL nonclose string in ' . $input );
        }

        // Finally, trim the result
        return trim( $output );
    }
    
    /**
     * Restore a single String Constant.
     *
     * @access private
     * @param string Token to lookup.
     * @return string Constant if token was found, otherwise return input as-is.
     */
    public static function restoreSqlConstant( $token )
    {
        return isset( self::$constTokens['#' . $token . '#'] ) ? self::$constTokens['#' . $token . '#'] : $token;
    }
    
    public static function normalizeSyntax( $sqlString )
    {
        // Make sure whitespaces are really blanks and only a single one.
        // It should slightly simplify and, more importantly, speed up
        // regular expressions used by the parser from this point on.
        $sqlString = preg_replace( '#\s+#', ' ', $sqlString );

        // Ignore backticks now, if any.
        $sqlString = str_replace( '`', '', $sqlString );

        // Trim whitespaces before "(", ",", ";" or ")".
        $sqlString = preg_replace( '# *([(),;])#', '$1', $sqlString );

        // Trim whitespaces after "(", "," or ";".
        $sqlString = preg_replace( '#([(,;]) *#', '$1', $sqlString );

        // Make sure there is a single whitespace between ")" and a word.
        $sqlString = preg_replace( '#(\))([a-zA-Z0-9_])#', '$1 $2', $sqlString );

        return $sqlString;
    }
    
    /**
     * Split SQL elements list.
     *
     * Format expected: element[,...]
     *
     * This method assumes no string constants are present (they were replaced with tokens).
     * Note this method needs to be used since some elements in the list may have commas
     * such as dec(x,y) or (key1,key2), so we can't simply explode(',', $elements_list).
     *
     * @param string Elements list.
     * @return array An array item for each element in the list.
     */
    public static function splitElementsList( $elementsList )
    {
        $elementsListLength = strlen( $elementsList );
        $elementsArray = array( );
        $element = '';
        $innerParenthesis = 0;
        for ( $i = 0; $i < $elementsListLength; $i++ )
        {
            $char = $elementsList[$i];

            if ( $innerParenthesis > 0 )
            {
                $element .= $char;

                if ( $char == ')' )
                {
                    $innerParenthesis--;
                }
                elseif ( $char == '(' )
                {
                    $innerParenthesis++;
                }
            }
            else
            {
                if ( $char == ',' )
                {
                    $elementsArray[] = trim( $element );
                    $element = '';
                }
                else
                {
                    $element .= $char;

                    if ( $char == '(' )
                    {
                        $innerParenthesis++;
                    }
                }
            }
        }
        $elementsArray[] = trim( $element );
        return $elementsArray;
    }
    

    /**
     * Generate a safe SQL identifier.
     *
     * This method may remove chars until it gets an identifier that is no longer than max length.
     * To shorten the string, it first removes lastest underscores, then removes trailing chars.
     * If possible it will not remove the first underscore, it might be part of the table prefix.
     *
     * @param string Indentifier.
     * @param string Suffix to append to identifier.
     * @return string New identifier
     */
    public static function getIdentifier( $identifier, $suffix = '' )
    {
        $maxLength = 30 - strlen( $suffix );
        while ( strlen( $identifier ) > $maxLength )
        {
            if ( ($i = strpos( $identifier, '_' )) === false || ($j = strrpos( $identifier, '_' )) === false || $i == $j )
            {
                break;
            }
            $identifier = substr( $identifier, 0, $j ) . substr( $identifier, $j + 1 );
        }
        while ( strlen( $identifier ) > $maxLength )
        {
            $identifier = substr( $identifier, 0, -1 );
        }
        return $identifier . $suffix;
    }
}
