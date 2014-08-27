<?php

/**
 * This is an attempt to write the keyword modifiers process using the functional programming paradigm
 */

/**
 * @function get_kwds_extract_func() is much like a class constructor.
 *	     It returns a function for managing template strings. That
 *	     function, in turn, when handed a template string, returns
 *	     a function that handles populating the template with the
 *	     supplied keywords.
 *
 * NOTE: choosing which template is to be used and handling columns
 *	 in that template is outside the scope of this function or
 *	 it's returned functions
 *
 * @param string $kwd_delim a single character used to delimit the
 *	  begining and end of a keyword pattern used by templates. 
 *	  NOTE: if $kwd_delim is either side of a bracket/brace the
 *	  	start and end delimiters will be the open and
 *	  	closing brackets/braces respectively
 *
 * @param string $mod_delim a single character used to delimit
 *	  keyword modifiers
 *
 * @param string $mod_param_delim a single character used to delimit
 *	  keyword modifier paramaters
 *
 * @param bool $case_sensitive whether or not to treat keywords as
 *	  case sensitive.
 *
 * @return function a function that takes a template string, extracts
 * 	   the keywords from the template string and returns a
 * 	   function that replaces the keywords in the template with
 * 	   the values supplied to the function
 */
function get_kwds_extract_func( $kwd_delim = '{' , $mod_delim = '^' , $mod_param_delim = ':' , $case_sensitive = false )
{
	$params = array( 'kwd_delim' , 'mod_delim' , 'mod_param_delim' );

	// do some validating on all the function paramaters
	for( $a = 0 ; $a < 3 ; $a += 1 )
	{
		if( !is_string( $input ) )
		{
			die( "The value for {$params[$a]} is not a string." );
		}
		if( strlen($input) !== 1 )
		{
			die( "The value for {$params[$a]} (\"{$$params[$a]}\") was more than, or less than one character." );
		}
		if( preg_match('/[a-z0-9\s_-]/i') )
		{
			die( "The value for {$params[$a]} (\"{$$params[$a]}\") was either an alpha-numeric, character a white-space character, an underscore or a hyphen and cannot be used for this purpose." );
		}

	}

	// if $kwd_delim is a bracket/brace make the $start_delim and
	// $end_delim the conventional open/close bracket pair
	switch( $kwd_delim )
	{
		case '{':
		case '}':	$start_delim = '{';
	       			$end_delim = '}';
			       	break;

		case '[':
		case ']':	$start_delim = '[';
				$end_delim = ']';
			       	break;

		case '(':
		case ')':	$start_delim = '(';
				$end_delim = ')';
			       	break;

		case '<':
		case '>':	$start_delim = '<';
				$end_delim = '>';
			       	break;

		default:	$start_delim = $end_delim  = $kwd_delim;
	}

	if( $mod_delim == $start_delim || $mod_delim == $end_delim )
	{
		die( '$mod_delim ('.$mod_delim.') cannot be the same as $kwd_delim ('.$start_delim.' or '.$end_delim.').' );
	}
	if( $mod_parm_delim == $start_delim || $mod_param_delim == $end_delim || $mod_param_delim == $mod_delim )
	{
		die( '$mod_param_delim ('.$mod_param_delim.') cannot be the same as $kwd_delim ('.$start_delim.' or '.$end_delim.') or $mod_delim ('.$mod_param.')' );
	}

	// build regexes for matching whole keywords, keyword modifiers and keyword modifier parameters
	/**
	 * @var string $kwd_regex Regular Expression for matching whole
	 *	keywords and modifiers (as a single block) if any.
	 */
	$kwd_regex = '`'.preg_quote($start_delim).'([^\s].*?)(?:(?<!\\\\)'.preg_quote($mod_delim).'(.*?))(?<!\\\\)'.preg_quote($end_delim).'`s';
	/**
	 * @var string $mod_delim_regex Regular Expression for splitting
	 *	keyword modifiers
	 */
	$mod_delim_regex = '`(?<!\\\\)'.preg_quote($mod_delim).'`s';
	/**
	 * @var string $mod_param_delim_regex Regular Expression for
	 *	splitting keyword modifier parameters
	 */
	$mod_param_delim_regex = '`(?<!\\\\)'.preg_quote($mod_param_delim).'`s';

	if( $case_sensitive !== true )
	{
		// if not case sensitive make all keywords upper case
		$fix_case = function( &$input )
		{
			foreach( $input as $key => $value )
			{
				$key_ = strtoupper($key);
				$input[$key_] = $value;
				unset($input[$key],$key_);
			}
		}
	}
	else
	{
		// don't do anything
		$fix_case = function( $input ) { return $input; };
	}

	/**
	 * @function kwds_extract_func() takes a template string, finds all the
	 *	     keywords and their modifiers and returns a function that
	 *	     can be used to populate the template
	 *
	 * NOTE: This is effectively a factory method that generates
	 * 	 populate_template() objects used for populating templates with
	 * 	 their appropriate keyword values as supplied by an array.
	 *
	 * @param string $tmpl a template whose keywords are to be extracted
	 *
	 * @return function a function that takes an array of key value pairs
	 *	   where the key is a keyword and the value is the replacement
	 *	   for that keyword. The keyword values are then used to replace
	 *	   the keywords in the template and a string containing the
	 *	   populated template is returned
	 */
	return function( $tmpl ) use ( $kwd_regex , $mod_delim_regex , $mod_param_delim_regex , $fix_case )
       	{
		if( !is_string($tmpl) || $tmpl == '' )
		{
			// $tmpl was not a valid template, just return an empty string
			// when populating
			return function( $input_array ) { return ''; };
		}

		if( preg_match_all( $kwd_regex , $tmpl , $keywords , PREG_SET_ORDER ) )
		{
			// OK we've got some keywords lets turn them into functions that
			// can be used to modify (if appropriate) the replacement value of
			// the keyword
			for( $a = 0 ; $a < count($keywords) ; $a += 1 )
			{
				/**
				 * @var string $keyword the name of the keyword to be replaced
				 */
				$keyword = $keywords[$a][1];
				/**
				 * @var string $keyword_str the full keyword including delimiters
				 *	and modifiers used in the string replacement for the
				 *	keyword value
				 */
				$keyword_str = $keywords[$a][0];
				/**
				 * @var string $modifiers the list of unsplit, unprocessed
				 *	keyword modifiers for that keywor instance
				 */
				$modifiers = $keywords[$a][2];
				if( !isset($kwd_array[$keyword]) )
				{
					/**
					 * @var array $kwd_array a two dimensional array containing the
					 *	list of keywords as the key to the top level of the array.
					 *	The second level is keyed to the original keyword string
					 *	including delimiters and modifiers. The value of the second
					 *	level is the function that modifies the supplied keyword
					 *	value before it is inserted into the template.
					 */
					$kwd_array[$keyword] = array();
				}

				if( $modifiers == '' )
				{
					// keyword had no modifiers just return the unmodified keyword value
					$kwd_array[$keyword][$keyword_str] = function($input) { return $input; } );
				}
				else
				{
					// split the modifiers into an array
					$modifiers = preg_split( $mod_delim_regex , $modifiers );
					$mod_func = null;
					$next = false;
					for( $a = 0 ; $a < count($modifiers) ; $a += 1 )
					{
						// split the modifier into it's name and paramaters
						$modifier_parts = preg_split( $mod_param_delim_regex , $modifiers[$a] );

						$func = get_kwdmod_func( $modifier_parts );

						// nest modifiers
						if( $next === true )
						{
							$mod_func = function( $input ) use ( $func , $mod_func )
					       		{
								return $func($mod_func($input));
							}
						}
						else
						{
							$mod_func = function($input) use ( $func )
					       		{
								return $func($input);
							}
						}

						$next = true;
					}

					$kwd_array[$keyword][$keyword_str] = $mod_func;
				}
			}
			$fix_case($kwd_array);
		}
		else
		{
			// no keywords were found, just return the original template when
			// populating
			return function( $input_array ) { return $tmpl; };
		}
		
		/**
		 * @function populate_template() takes an array of strings where the
		 *	     key matches keywords in the template and the values are
		 *	     the replacement values for those keywords and returns the
		 *	     template with the keywords replaced with the appropriate
		 *	     values.
		 *
		 * @param array $input_array a list of key/value pairs where the key
		 *	  is a keyword found in the template and the value is the
		 *	   replacement value for that keyword.
		 *	  NOTE: keywords supplied but not in the template will just be
		 *	  	ignored
		 *
		 * @use string $tmpl raw template string
		 * @use array $kwd_array list of keywords their original string and
		 * 	the function that returns the modified value for the supplied
		 * 	keyword.
		 *
		 * @return string template populated by the supplied keywords.
		 */
		return function( $input_array ) use ( $tmpl , $kwd_array , $fix_case )
	       	{
			if( !is_array($input_array) )
			{
				die( '$input_array MUST be an array. '.gettype($input_array).' given.' );
			}
			$find = array();
			$replace = array();

			// if template is not case sensitive, make input keywords case
			// insensitive too
			$fix_case($input_array);

			foreach( $kwd_array as $key => $value )
			{
				if( is_array($value) && !empty($value) )
				{
					foreach( $value as $kwd_str => $kwd_func )
					{
						$find[] = $kwd_str;
						if( isset($input_array[$key]) )
						{
							$replace[] = $kwd_func($input_array[$key]);
						}
						else
						{
							$replace[] = '';
						}
				}
			}
			return str_replace( $find , $replace , $tmpl );
		}
	}

}


/**
 * @function get_kwdmod_func() returns a function to be
 * 	     used to modify the output of keywords
 *
 * @param string $modifier_name the name of the modifier
 *
 * @param string $action1 a regex or find string or match value
 *
 * @param string $action2 a regex replacement string or a
 *	  replacement string, or the if value if a previous match
 *	   was made
 *
 * @param string $action3 a replacement if the regex or match value
 *	  was NOT found.
 *
 * @return function a function takes a single input string value to
 *	   be modified
 */
function get_kwdmod_func( $modifier_parts )
{
	$modifier_name = $modifier_parts[0];
	$action1 = isset($modifier_parts[1])?$modifier_parts[1]:false;
	$action2 = isset($modifier_parts[2])?$modifier_parts[2]:false;
	$action3 = isset($modifier_parts[3])?$modifier_parts[3]:false;

	if( is_string($modifier_name) )
	{
		$modifier_name = trim(strolower(preg_replace('`[^a-z]+`','',$modifier_name)));
		
		$unescape_kwd_specialchars = function($input)
		{
			return str_replace(array('\:','\^','\%'),array(':','^','%'),$input); // unescape keyword special characters
		}

		switch($modifier_name)
		{
			case 'characters':
			case 'chars':
			case 'maxchars':
				$maxlength = 1000;
				if( is_numeric($action1) && $action1 > 0 )
				{
					$maxlength = $action1;
				}
				$stripcode = get_kwdmod_func('text',$action2);
				return function($input) use ($maxlength) {
						$input = $stripcode($input);
						if( strlen($input) > $maxlength ) {
						       	$input = substr($input,0,$maxlenght);
						}
						return $input;
					}
				break;


			case 'class':	// make ok for use as value in Class
			case 'csssafe':
			case 'id':	// make ok for use as value in ID
					// (NOTE: functional programming requires functions to be stateless
					//	  to create IDs the function would need to be aware of all
					//	  other IDs created. Thus making it stateful and breaking
					//	  the functional programming paradigm
				return function($input) {
					$find = array(
						 '/(?:^[^a-z0-9]+|[^_a-z0-9-]+$/i'	// find bad characters from the begining and end of a string
											// NOTE:  strings cannot start with hyphens or underscores
											//	  ('-' or '_') as these are generally reserved for
											//	  browser specific selectors
						,'/^(?=[0-9])/'		// find numbers of the start of the string
						,'/[^_a-z0-9-]+/i'	// find all bad characters within a string
						,'/[_-]{3}/'		// clean up multi underscores
					);
					$replace = array(
						 ''	// strip bad characters from begining and end of the string
						,'A_'	// make string start valid for class name
						,'_'	// replace multiple bac chars with a single underscore
						,'_'	// clean up
					);
					return preg_replace( $find , $replace , $input );
				};
				break;

			case 'dateformat':
			case 'formatdate':
				if( is_string($action1) && $action1 != '' )
				{
					return function($input) use ($action1,$action2) {
						if( $action2 == 'now' ) {
							$input = time(); // just give me a current time stamp regardless
						       			 // of the value of the keyword
						}
						if( is_int($input) || is_numeric($input) )
						{ // input is a viable timestamp
							$action1 = $unescape_keyword_specialchars($action1); // unescape keyword special characters
							$input = date( $action1 , $input );
						}
						return $input;
					}
				}
				break;

			case 'empty':
				return function($input) use ($action1,$action2) {
					if( empty($input) ) { return $action1; }
					elseif( $action2 !== false ) { return $action2; }
					else { return $input; }
				}
				break;

			case 'heading': // convert to lower case then UPPER CASE first letter in every word
			case 'titleize': // for mySource matrix compatibility
				return function($input) { ucwords(strtolower($input)); };
				break;

			case 'lowercase': // convert string to lower case
				return function($input) { strtolower($replace); };
				break;

			case 'contains':
			case 'doesntcontains':
			case 'match':
			case 'notmatch':
				if( is_string($action1) && $action1 != '' )
				{
					if( $modifier_name == 'contains' )
					{
						return function($input) use ($action1,$action2,$action3) {
							if( substr_count($input,$action1) > 0 ) { return $action2; }
							elseif( $action3 !== false ) { return $action3; }
							else { return $input; }
						}
					}
					elseif( $modifier_name == 'doesntcontain' )
					{
						return function($input) use ($action1,$action2,$action3) {
							if( substr_count($input,$action1) == 0 ) { return $action2; }
							elseif( $action3 !== false ) { return $action3; }
							else { return $input; }
						}
					}
					elseif( $modifier_name == 'notmatch')
					{
						return function($input) use ($action1,$action2,$action3) {
							if( $action1 != $input ) { return $action2; }
							elseif( $action3 !== false ) { return $action3; }
							else { return $input; }
						}
					}
					else
					{
						return function($input) use ($action1,$action2,$action3) {
							if( $action1 == $input ) { return $action2; }
							elseif( $action3 !== false ) { return $action3; }
							else { return $input; }
						}
					}
				}
				break;

			case 'maxwords':
			case 'words':
			case 'maxsentance':
				if( is_numeric($action1) )
				{
					$action1 = round(trim($action1));
					if( $action2 === false )
					{
						$action2 = 1;
					}
					$stripcode = get_kwdmod_func('stripcode',$action2);
					if( $modifier_name == 'maxwords' || $modifier_name == 'words' )
					{
						$regex = '`^((?:[^\r\n!?.]+(?:[.?!]+|[\r\n]+)[\t \r\n]*){0,'.$action1.'}).*$`s';
					}
					else
					{
						$regex = '`^([-\w]+(?:\W+[-\w]+){0,'.$action1.'}).*$`is'
					}
					return function($input) use ($regex) { return preg_replace( $regex , '\1' , $stripcode($input) ); };
				}
				break;

			case 'nocomment': // strip HTML comments
			case 'stripcomment':	// strips HTML comments
				return function( $input ) { return preg_replace('`<!--.*?-->|/\*.*?\*/`s','',$input); };
				break;

			case 'nomultispace': // make multiple spaces (including lines and tabs) into a single space
			case 'singlespace':
				$output = function($input) { preg_replace('/[\r\n\t ]+/',' ',$lines); };
				break;

			case 'notempty':
				return function($input) use ($action1,$action2) {
					if( !empty($input) ) { return $action1; }
					elseif( $action2 !== false ) { return $action2; }
					else { return $input; }
				};
				break;

			case 'pregmatch':
			case 'pregreplace':
				if( is_string($action1) && $action1 != '' )
				{
					@preg_match($action1,'');
					if( preg_last_error() == PREG_NO_ERROR )
					{
						$action1 = $unescape_keyword_specialchars($action1); // unescape keyword special characters
						if( $modifier_name == 'pregmatch' )
						{
							return function($input) use ($action1,$action2,$action3) {
								if( preg_match("`$action1`s",$input)) { return $action2; }
								elseif( $action3 !== false ) { return $action3; }
								else { return $input; }
							}
						}
						elseif( $modifier_name == 'notpregmatch' )
						{
							return function($input) use ($action1,$action2,$action3) {
								if( !preg_match("`$action1`s",$input)) { return $action2; }
								elseif( $action3 !== false ) { return $action3; }
								else { return $input; }
							}
						}
						elseif( $modifier_name == 'pregreplace' )
						{
							$action2 = $unescape_keyword_specialchars($action2); // unescape keyword special characters
							return function($input) use ($action1,$action2) {
								return preg_replace("`$action1`",$action2,$input);
							}
						}
					}
				}
				break;

			case 'stripimage':	// strips image tags
				return function( $input ) { return preg_replace('`<img[^>]*>`si','',$input); };
				break;

			case 'striplink':	// strips link tags only (leaving content)
				return function( $input ) { return preg_replace('`<a\s+[^>]*>`si','',$input); };
				break;

			case 'stripwholelink':	// removes link and link text
				return function( $input ) { return preg_replace('`<a\s+[^>]*>.*?</a>`si','',$input); };
				break;

			case 'stripnonbody':	// strips non body tags
				return function( $input ) { return preg_replace('`^.*?<body\s+[^>]*>|</body>.*$`si','',$input); };
				break;

			case 'striponevent':	// strips javascript event attributes from within elements
				// TODO work out how to use callback functions in functional programming 
				return function($input) { return preg_replace_callback( '`(<[a-z]+\s+)([^>]+)(?=>)`i' , function($matches) {
						return $matches[1].preg_replace( '`\s+on[a-z]+=(\'|").*?\1`i','',$matches[2]);
					}
					,$input
				)};
				break;

			case 'stripscript':	// strips javascript tags and their contents
				return function( $input ) { return preg_replace('`<script\s+[^>]*>.*?</script>|\script=([\'"]).*?\1`si','',$input); };
				break;

			case 'stripstyle':	// strips style tags and their contents
				return function( $input ) { return preg_replace('`<style\s+[^>]*>.*?</style>|\style=([\'"]).*?\1`si','',$input); };
				break;

			case 'nolines':
				if( !is_int($action1) || $action1 <= 0 )
				{
					$max_lines = 2;
				}
				else
				{
					$max_lines = $aciton1;
				}
				return function( $input ) use ( $max_lines) {
					return preg_replace(
						 array(
							 '`[\t ]+(?=[\r\n])`'
							,'`((?:\r\n|\n\r|\r|\n){'.$max_lines.'})(?:\r\n|\n\r|\r|\n)+`'
						 )
						 array(
							 ''
							,'\1'
						 )
						,$input
					);
				});
				break;

			case 'sentance': // convert to lower case the uppercase the first character in the string
			case 'capitalize': // for mySource matrix compatibility
				return function($input) {
					$replace = function( $matches ) {
						return $matches[1].strtoupper($matches[2]);
					}

				       	preg_replace_callback( '`(?<=^|[.?!])(\s+)([a-z])(?=[a-z]*)`i',$replace,$input);
				};
				break;

			case 'space': // convert underscores to spaces
				return function($input) { return str_replace('_',' ',$input); };
				break;

			case 'text':
			case 'plaintext': // make string plain text
				$stripcomments = get_kwdmod_func('stripcomments');
				$stripstyle = get_kwdmod_func('stripstyle');
				$stripscript = get_kwdmod_func('stripscript');
				$stripcdata = get_kwdmod_func('stripcdata');
				return function($input) use ( $stripcomments , $stripstyle , $stripscript , $stripcdata ) {
					return $stripcomments(
						$stripstyle(
							$stripscript(
								$stripcdata(
									$input
								)
							)
						)
					);
				};
				break;

			case 'stripcdata':
				return function($input) { return preg_replace( '/(?:\/\/[\t ]*)?<!\[CDATA\[.*?\]\]>/is' , '' , $input ); };
				break;

			case 'trim': // remove white space from begining and end of string
				return function($input) { return trim($input); }; }
				break;

			case 'underscore': // convert spaces to underscores
				return function($input) { return str_replace(' ','_',$replace); };
				break;

			case 'uppercase': // convert string to upper case
				return function($input) { return strtoupper($replace); };
				break;

			case 'urldecode':
				return function($input) { return  urldecode($replace); };
				break;

			case 'urlencode':
				return function($input) { return  urlencode($replace); };
				break;
		}
	}
	return function( $input ) { return $input; };
}

