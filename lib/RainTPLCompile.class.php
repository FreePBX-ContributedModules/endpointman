<?php
namespace FreePBX\modules;

/**
 * Project: RainTPL, compile HTML template to PHP
 *  
 * File: rain.tpl.compile.class.php
 * 
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @link http://www.raintpl.com
 * @author Federico Ulfo <rainelemental@gmail.com>
 * @version 1.9.1
 * @copyright 2006 - 2010 Federico Ulfo | www.federicoulfo.it
 * @package Rain
 */




/**
 *
 * FALSE more security (DEFAULT)
 * TRUE enable <?php ?> tag in templates.
 * 
 */

define( "RAINTPL_PHP_ENABLED", true );



/**
 * Rain TPL version
 */

define( "RAINTPL_VERSION", '1.9.1' );






/**
 * Template engine, compile HTML template to PHP, cache templates, wysiwyg: change image src to the right path.
 * 
 * @access private
 *
 */

class RainTPLCompile{
	
	
	/**
	 * Regular expression pattern
	 * @access private
	 * @var string
	 */
	var $split_pattern = '/(\{(?:loop(?:\s+)name="(?:.*?)")\})|(\{(?:\/loop)\})|(\{(?:if(?:\s+)condition="(?:.*?)")\})|(\{(?:elseif(?:\s+)condition="(?:.*?)")\})|(\{(?:else)\})|(\{(?:\/if)\})|(\{noparse\})|(\{\/noparse\})|(\{ignore\})|(\{\/ignore\})|(\{include="(?:.*?)"\})/';
	

	/**
	 * Template dir
	 */
	var $tpl_dir = null;


	/**
	 * Base directory of path substitution
	 */
	var $base_dir = null;


	/**
	 * Compile and write the compiled template file
	 *
	 * @access private
	 * @param string $tpl_name
	 * @param string $tpl_dir
	 */
	
	function compileFile( $tpl_name, $tpl_dir, $tpl_compile_dir = null, $base_dir = null ){
		$this->tpl_dir 	= $tpl_dir;
		$this->base_dir = $base_dir;
		
		// delete all the file with similar name
		if( $compiled_files = glob( $tpl_compile_dir . $tpl_name . '*.php' ) )
			foreach( $compiled_files as $file_name )
				unlink( $file_name );

		//read template file
		$template_code = file_get_contents( $tpl_dir . $tpl_name . '.' . TPL_EXT );

		//xml substitution
		$template_code = preg_replace( "/\<\?xml(.*?)\?\>/", "##XML\\1XML##", $template_code );

		//if tag are disabled
		if( !RAINTPL_PHP_ENABLED )
			$template_code = str_replace( array("<?","?>"), array("&lt;?","?&gt;"), $template_code );

		//xml substitution
		$template_code = preg_replace( "/\#\#XML(.*?)XML\#\#/", "<?php echo '<?xml' . stripslashes('\\1') . '?>'; ?>", $template_code );

		//compile template
		$template_compiled = $this->compileTemplate( $template_code, $tpl_name );
		$template_compiled = "<?php if(!defined('IN_RAINTPL')){exit('Hacker attempt');}?>" . $template_compiled;

		//write compiled file
		$filename = $tpl_compile_dir . $tpl_name . "_" . filemtime( $tpl_dir . '/' . $tpl_name . '.' . TPL_EXT ) . ".php";
		
		$dir = explode( "/", $tpl_compile_dir );
		for( $i=0, $base=""; $i<count($dir);$i++ ){
			$base .= $dir[$i] . "/";
			if( !is_dir($base) )
				mkdir( $base );
		}

		file_put_contents( $filename, $template_compiled );
		
		
	}
	

	/**
	 * Compile the template
	 *
	 * @param string $template_code
	 * @param string template_name
	 * @param string
	 */
	function compileTemplate( $template_code, $tpl_name ){

		//get all the tags into the template
		$template_code = preg_split ( $this->split_pattern, $template_code, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
		
		//get the compiled code
		$compiled_code = $this->compileCode( $template_code );
		
		return $compiled_code;

	}
	
	/**
	 * Compile the code
	 *
	 * @access private
	 * @param string $parsed_code Array that contain html and tag
	 * @return string compiled code
	 */
	function compileCode( $parsed_code ){

		//variables initialization
		$parent_loop[ $level = 0 ] = $loop_name = $compiled_code = $compiled_return_code = null;
	 	$open_if = $comment_is_open = $ignore_is_open = 0;

	 	//read all parsed code
	 	while( $html = array_shift( $parsed_code ) ){
	 		
	 		//indentation
	 		for( $space_counter = 0, $space = ""; $space_counter < $level + $open_if; $space_counter++, $space .= "	" );
	 		
	 		//close ignore tag
	 		if( !$comment_is_open && preg_match( '/\{\/ignore\}/', $html ) )
	 			$ignore_is_open = false;	
	 			
	 		//code between tag ignore id deleted
	 		elseif( $ignore_is_open ){
	 			//non faccio niente
	 		}

	 		//close no parse tag
	 		elseif( preg_match( '/\{\/noparse\}/', $html ) )
	 			$comment_is_open = false;	
	 			
	 		//code between tag noparse is not compiled
	 		elseif( $comment_is_open ){
 				$compiled_code .= $html;
	 		}

	 		//ignore
	 		elseif( preg_match( '/\{ignore\}/', $html ) )
	 			$ignore_is_open = true;

	 		//noparse
	 		elseif( preg_match( '/\{noparse\}/', $html ) )
	 			$comment_is_open = true;
	 		
			//include tag
			elseif( preg_match( '/(?:\{include="(.*?)"\})/', $html, $code ) ){
			
				//variables substitution
				$include_var = $this->var_replace( $code[ 1 ], $left_delimiter = null, $right_delimiter = null, $php_left_delimiter = '".' , $php_right_delimiter = '."', $this_loop_name = $parent_loop[ $level ] );

				//dynamic include
				$compiled_code .= "<?php\n" .
								 $space . "	\$tpl = new RainTPL( RainTPL::\$tpl_dir . dirname(\"{$include_var}\"));\n" .
								 $space . "	\$tpl->assign( \$var );\n" .
								 $space . "	" . ( !$this_loop_name ? null : "\$tpl->assign( \"key\", \$key[{$this_loop_name}] );\n" . "\$tpl->assign( \"value\", \$value[{$this_loop_name}] );\n" ) .
								 $space . "	\$tpl->draw(basename(\"{$include_var}\"));" . "\n" .
								 "?>";
			}
	 			
	 		//loop
	 		elseif( preg_match( '/(?:\{loop(?:\s+)name="(.*?)"\})/', $html, $code ) ){
	 			
	 			//increase the loop counter
	 			$level++;
	 			
	 			//name of this loop
				$parent_loop[ $level ] = $level;

				$var = $this->var_replace( '$' . $code[ 1 ], "","", "","", $level-1 );
					
				//loop variables
				$counter = "\$counter$level";	// count iteration
				$key = "\$key$level";			// key
				$value = "\$value$level";		// value
				
				//loop code
				$compiled_code .=  "<?php" . "\n" .
										$space . "	if( isset( $var ) && is_array( $var ) ){" . "\n" .
										$space . "		$counter = 0;" . "\n" .
										$space . "		foreach( $var as $key => $value ){ " . "\n" .
										"?>";

			}
			
			//close loop tag
			elseif( preg_match( '/\{\/loop\}/', $html ) ){
				//iterator
				$counter = "\$counter$level";
				
				//decrease the loop counter
				$level--;				
				
				//close loop code
				$compiled_code .=  "<?php" . "\n" .
										$space . "		$counter++;" . "\n" .
										$space . "	}" . "\n" .
										$space . "}" . "\n" .
										"?>";
			}
			
			//if
			elseif( preg_match( '/(?:\{if(?:\s+)condition="(.*?)"\})/', $html, $code ) ){
				
				//increase open if counter (for intendation)
				$open_if++;
				
				//condition attribute
				$condition = $code[ 1 ];

				//variable substitution into condition (no delimiter into the condition)
				$parsed_condition = $this->var_replace( $condition, $tag_left_delimiter = '', $tag_right_delimiter = '', $php_left_delimiter = null, $php_right_delimiter = null, $parent_loop[ $level ] );				

				//if code
				$compiled_code .=   "<?php" . "\n" .
										 $space . "	if( $parsed_condition ){" . "\n" .
										 "?>";
			}

			//elseif
			elseif( preg_match( '/(?:\{elseif(?:\s+)condition="(.*?)"\})/', $html, $code ) ){
				
				//increase open if counter (for intendation)
				$open_if++;
				
				//condition attribute
				$condition = $code[ 1 ];
				
				//variable substitution into condition (no delimiter into the condition)
				$parsed_condition = $this->var_replace( $condition, $tag_left_delimiter = '', $tag_right_delimiter = '', $php_left_delimiter = null, $php_right_delimiter = null, $parent_loop[ $level ] );				

				//elseif code
				$compiled_code .=   "<?php" . "\n" .
										 $space . "}" . "\n" .
										 $space . "	elseif( $parsed_condition ){" . "\n" . 
										 "?>";
			}
			
			//else
			elseif( preg_match( '/\{else\}/', $html ) ){

				//else code
				$compiled_code .=   "<?php" . "\n" .
										 $space . "}" . "\n" .
										 $space . "else{" . "\n" .
										 "?>";

			}
						
			//close if tag
			elseif( preg_match( '/\{\/if}/', $html ) ){
				
				//decrease if counter
				$open_if--;
				
				// close if code 
				$compiled_code .=   "<?php" . "\n" .
										 $space . "}" . "\n" .
										 "?>";
				
			}

			//all html code
			else{

				//path replace (src of img, background and href of link)
				$html = $this->path_replace( $html );

				//variables substitution (es. {$title})
				$compiled_code .= $this->var_replace( $html, $left_delimiter = '\{', $right_delimiter = '\}', $php_left_delimiter = '<?php echo ', $php_right_delimiter = ';?>', $parent_loop[ $level ] );

			}
		}

		
		return $compiled_code;
	}
	
	
	
	/**
	 * Path replace (src of img, background and href of link)
	 * url => template_dir/url
	 * url# => url
	 * http://url => http://url
	 * 
	 * @param string $html 
	 * @return string html sostituito
	 */
	function path_replace( $html ){
		$exp = array( '/src=(?:")http\:\/\/([^"]+?)(?:")/i', '/src=(?:")([^"]+?)#(?:")/i', '/src="(.*?)"/', '/src=(?:\@)([^"]+?)(?:\@)/i', '/background=(?:")http\:\/\/([^"]+?)(?:")/i', '/background=(?:")([^"]+?)#(?:")/i', '/background="(.*?)"/', '/background=(?:\@)([^"]+?)(?:\@)/i', '/<link(.*?)href=(?:")http\:\/\/([^"]+?)(?:")/i', '/<link(.*?)href=(?:")([^"]+?)#(?:")/i', '/<link(.*?)href="(.*?)"/', '/<link(.*?)href=(?:\@)([^"]+?)(?:\@)/i' );
		$sub = array( 'src=@http://$1@', 'src=@$1@', 'src="' . $this->base_dir . '\\1"', 'src="$1"', 'background=@http://$1@', 'background=@$1@', 'background="' . $this->base_dir . '\\1"', 'background="$1"', '<link$1href=@http://$2@', '<link$1href=@$2@' , '<link$1href="' . $this->base_dir  . '$2"', '<link$1href="$2"' );
		//return preg_replace( $exp, $sub, $html );
                return($html);
	}



	/**
	 * Variable substitution
	 *
	 * @param string $html Html
	 * @param string $tag_left_delimiter default {
	 * @param string $tag_right_delimiter default }
	 * @param string $php_left_delimiter default <?php=
	 * @param string $php_right_delimiter  default ;?>
	 * @param string $loop_name Loop name
	 * @return string Replaced code
	 */
	function var_replace( $html, $tag_left_delimiter, $tag_right_delimiter, $php_left_delimiter = null, $php_right_delimiter = null, $loop_name = null ){

		//all variables
		$html = preg_replace( '/\{\#(\w+)\#\}/', $php_left_delimiter . '\\1' . $php_right_delimiter, $html );
		preg_match_all( '/' . $tag_left_delimiter . '\$(\w+(?:\.\${0,1}(?:\w+))*(?:\[\${0,1}(?:\w+)\])*(?:\-\>\${0,1}(?:\w+))*)(.*?)' . $tag_right_delimiter . '/', $html, $matches );

		for( $i = 0; $i < count( $matches[ 0 ] ); $i++ ){

			//complete tag ex: {$news.title|substr:0,100}
			$tag = $matches[ 0 ][ $i ];			

			//variable name ex: news.title
			$var = $matches[ 1 ][ $i ];
			
			//function and parameters associate to the variable ex: substr:0,100
			$extra_var = $matches[ 2 ][ $i ];
			
			//function associate to variable
			$function_var = ( $extra_var and $extra_var[0] == '|') ? substr( $extra_var, 1 ) : null;
			
			//variable path split array (ex. $news.title o $news[title]) or object (ex. $news->title)
			$temp = preg_split( "/\.|\[|\-\>/", $var );
			
			//variable name
			$var_name = $temp[ 0 ];
			
			//variable path
			$variable_path = substr( $var, strlen( $var_name ) );
			
			//parentesis transform [ e ] in [" e in "]
			$variable_path = str_replace( '[', '["', $variable_path );
			$variable_path = str_replace( ']', '"]', $variable_path );
			
			//transform .$variable in ["$variable"]
			$variable_path = preg_replace('/\.\$(\w+)/', '["$\\1"]', $variable_path );
			
			//transform [variable] in ["variable"]
			$variable_path = preg_replace('/\.(\w+)/', '["\\1"]', $variable_path );

			//if there's a function
			if( $function_var ){
				
				//split function by function_name and parameters (ex substr:0,100)
				$function_split = explode( ':', $function_var, 2 );
				
				//function name
				$function = $function_split[ 0 ];
				
				//function parameters
				$params = ( isset( $function_split[ 1 ] ) ) ? $function_split[ 1 ] : null;

			}
			else
				$function = $params = null;

				
			//if it is inside a loop
			if( $var_name == 'GLOBALS' )
				$php_var = '$GLOBALS' . $variable_path;
			elseif( $var_name == '_SESSION' )
				$php_var = '$_SESSION' . $variable_path;
			elseif( $var_name == '_COOKIE' )
				$php_var = '$_COOKIE' . $variable_path;
			elseif( $var_name == '_SERVER' )
				$php_var = '$_SERVER' . $variable_path;
			elseif( $var_name == '_GET' )
				$php_var = '$_GET' . $variable_path;
			elseif( $var_name == '_POST' )
				$php_var = '$_POST' . $variable_path;
			elseif( $loop_name ){
				//verify the variable name
				if( $var_name == 'key' )
					$php_var = '$key' . $loop_name;
				elseif( $var_name == 'value' )
					$php_var = '$value' . $loop_name . $variable_path;
				elseif( $var_name == 'counter' )
					$php_var = '$counter' . $loop_name;
				else
					$php_var = '$var["' . $var_name . '"]' . $variable_path;
			}
			else
				$php_var = '$var["' . $var_name . '"]' . $variable_path;
				
			if( isset( $function ) )
				$php_var = $php_left_delimiter . ( $params ? "( $function( $php_var, $params ) )" : "$function( $php_var )" ) . $php_right_delimiter;
			else
				$php_var = $php_left_delimiter . $php_var . $extra_var . $php_right_delimiter;

			$html = str_replace( $tag, $php_var, $html );

		}
		
		
		
		return $html;
	}

}
	
?>