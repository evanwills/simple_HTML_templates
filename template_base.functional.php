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
		$param_name = $params[$a];
		$input = $$param_name;
		if( !is_string( $input ) )
		{
			die( "The value for $param_name is not a string." );
		}
		$input_len = strlen($input);
		if( $input_len < 1 )
		{
			die( "The value for $param_name (\"$input\") was less than one character." );
		}
		if( $a > 0 && $input_len > 1 )
		{
			die( "The value for $param_name (\"$input\") was more than." );
		}
		if( preg_match('/[a-z0-9\s_-]/i',$input) )
		{
			die( "The value for $param_name (\"$input\") was either an alpha-numeric, character a white-space character, an underscore or a hyphen and cannot be used for this purpose." );
		}
	}

	if( !is_bool($case_sensitive) )
	{
		$case_sensitive = false;
	}

	// some templating systems use multiple characters as delimiters for their keywords
	// to enable this we take a single delimiter and extrapolate it into opening and
	//  closing delimiters which are mirror images of each other.
	if( is_string($kwd_delim) && preg_match_all('`[^a-z0-9\s_-]`',$kwd_delim,$delim_bits) )
	{
		$start_delim = '';
		$end_delim = '';
		$open = null;
		$a = '';
		$b = '';
		$open_close = array( '{' => '}' , '<' => '>' , '[' => ']' , '(' => ')' );
		$die_msg = 'cannot use an open bracket as part of a delimiter that uses a closed bracket in the same delimiter';
		for( $c = 0 ; $c < count($delim_bits[0]) ; $c += 1 )
		{
			if( isset( $open_close[$delim_bits[0][$c]] ) && $open !== false )
			{
				if( $open !== false )
				{
					$open_ = true;
					$_a = $delim_bits[0][$c];
					$_b = $open_close[$delim_bits[0][$c]];
				}
				else
				{
					die( $die_msg );
				}
			}
			elseif( $_a = array_search( $delim_bits[0][$c],$open_close ) && $open !== true )
			{
				if( $open !== true )
				{
					$open_ = false;
					$_b = $delim_bits[0][$c];
				}
				else
				{
					die( $die_msg );
				}
			}
			elseif( $open === null )
			{
				$open_ = null;
				$_b = $_a = $delimb_bits[0][$c];
			}
			$start_delim .= $_a;
			$end_delim = $_b.$end_delim;
		}
		if( $start_delim == '' || $end_delim == '' )
		{
			die( "'$kwd_delim' contained illegal characters or no characters at all." );
		}
	}


	if( $mod_delim == $start_delim || $mod_delim == $end_delim )
	{
		die( '$mod_delim ('.$mod_delim.') cannot be the same as $kwd_delim ('.$start_delim.' or '.$end_delim.').' );
	}
	if( $mod_param_delim == $start_delim || $mod_param_delim == $end_delim || $mod_param_delim == $mod_delim )
	{
		die( '$mod_param_delim ('.$mod_param_delim.') cannot be the same as $kwd_delim ('.$start_delim.' or '.$end_delim.') or $mod_delim ('.$mod_param.')' );
	}


	/**
	 * @var string $mod_delim_regex Regular Expression for splitting
	 *	keyword modifiers
	 */
	$mod_delim_regex = '(?<!\\\\\\\\)'.preg_quote($mod_delim);


	// build regexes for matching whole keywords, keyword modifiers and keyword modifier parameters
	/**
	 * @var string $kwd_regex Regular Expression for matching whole
	 *	keywords and modifiers (as a single block) if any.
	 */
	$kwd_regex = '`'.preg_quote($start_delim).'([^\s].*?)(?:'.$mod_delim_regex.'(.*?))?(?<!\\\\\\\\)'.preg_quote($end_delim).'`s';

	/**
	 * @var string $mod_param_delim_regex Regular Expression for
	 *	splitting keyword modifier parameters
	 */
	$mod_param_delim_regex = '`(?<!\\\\\\\\)'.preg_quote($mod_param_delim).'`s';

	$mod_delim_regex = "`$mod_delim_regex`s";

	if( $case_sensitive === true )
	{
		$kwd_regex .= 'i';
	}

	if( $case_sensitive !== true )
	{
		// if not case sensitive make all keywords upper case
		$fix_case = function( &$input ) {
			foreach( $input as $key => $value )
			{
				$key_ = strtoupper($key);
				$input[$key_] = $value;
				unset($input[$key],$key_);
			}
		};
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
				$modifiers = isset($keywords[$a][2])?$keywords[$a][2]:'';
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
					$kwd_array[$keyword][$keyword_str] = function($input){ return $input; };
				}
				else
				{
					// split the modifiers into an array
					$modifiers = preg_split( $mod_delim_regex , $modifiers );
					$mod_func = null;
					$next = false;
					for( $b = 0 ; $b < count($modifiers) ; $b += 1 )
					{
						// split the modifier into it's name and paramaters
						$modifier_parts = preg_split( $mod_param_delim_regex , $modifiers[$b] );

						$func = get_kwdmod_func( $modifier_parts );

						// nest modifiers
						if( $next === true )
						{
							$tmp_mod_func = function( $input ) use ( $func , $mod_func )
							{
								return $func( $mod_func($input) );
							};
							$mod_func = $tmp_mod_func;
						}
						else
						{
							$mod_func = $func;
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
			return function( $input_array ) use ( $tmpl ) { return $tmpl; };
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
		return function( $input_array = array() ) use ( $tmpl , $kwd_array , $fix_case )
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
			}

			return str_replace( $find , $replace , $tmpl );
		};
	};

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
		$modifier_name = trim(strtolower(preg_replace('`[^a-z]+`','',$modifier_name)));

		$unescape_kwd_specialchars = function($input)
		{
			return str_replace(array('\:','\^','\%'),array(':','^','%'),$input); // unescape keyword special characters
		};

		switch($modifier_name)
		{
			case 'add':
				if( is_numeric($action1) )
				{
					return function($input) use ( $action1 ) {
						if( is_numeric($input) )
						{
							return ( $action1 + $input );
						}
						return $input;
					};
				}
				break;

//			case 'capitalize': see 'sentance'

			case 'cdata':
				return function( $input ) {
					return "<![CDATA[$input]]>";
				};
				break;

			case 'ceil':
			case 'floor':// for mySource matrix compatibility
				return function( $input ) use ( $modifier_name ) {
					if( is_numeric($input) )
					{
						return $modifier_name($input);
					}
					return $input;
				};
				break;

			case 'characters': // strips all code then
			case 'chars':
			case 'maxchars':
				if( is_numeric($action1) && $action1 > 0 )
				{
					if( $action2 === false )
					{
						return function($input) use ($action1) {
							$input = $stripcode($input);
							if( strlen($input) > $action1 ) {
									$input = substr($input,0,$action1);
							}
							return $input;
						};
					}
					else
					{
						return function($input) use ($action1) {
							if( strlen($input) > $action1 ) {
									$input = substr($input,0,$action1);
							}
							return $input;
						};
					}
				}
				break;

			case 'charcount':
				return function( $input ) { return strlen($input); };
				break;

			case 'csssafe':	// make ok for use as value in Class
			case 'class':
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

			case 'contains':
				if( is_string($action1) && $action1 != '' )
				{
					if( $action3 !== false )
					{
						return function($input) use ($action1,$action2,$action3) {
							if( substr_count($input,$action1) > 0 ) { return $action2; }
							else { return $action3; }
						};
					}
					else
					{
						return function($input) use ($action1,$action2) {
							if( substr_count($input,$action1) > 0 ) { return $action2; }
							else { return $input; }
						};
					}
				}
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
					};
				}
				break;

			case 'decrement':
				return function($input) {
					if( is_numeric($input) )
					{
						return ( $input - 1 );
					}
					return $input;
				};
				break;

			case 'divide':
				if( is_numeric($action1) )
				{
					$subject = $action1;
					return function($input) use ( $subject ) {
						if( is_numeric($input) )
						{
							return ( $subject / $input );
						}
						return $input;
					};
				}
				break;

			case 'doesntcontains':
				if( is_string($action1) && $action1 != '' )
				{
					if( $action3 !== false )
					{
						return function($input) use ($action1,$action2,$action3) {
							if( substr_count($input,$action1) == 0 ) { return $action2; }
							else { return $action3; }
						};
					}
					else
					{
						return function($input) use ($action1,$action2) {
							if( substr_count($input,$action1) == 0 ) { return $action2; }
							else { return $input; }
						};
					}
				}
				break;

			case 'empty':
				if( $action2 !== false )
				{
					return function($input) use ($action1,$action2) {
						if( empty($input) ) { return $action1; }
						else { return $action2; }
					};
				}
				else
				{
					return function($input) use ($action1,$action2) {
						if( empty($input) ) { return $action1; }
						else { return $input; }
					};
				}
				break;

//			case 'eq': // see 'match'
//			case 'equals': // see 'match'

//			case 'escapehtml' see htmlspecialchars

// 			case 'floor': see 'ceil'

//			case 'formatdate': see 'dateformat'

			case 'gt':
			case 'gte':
			case 'lt':
			case 'lte':
				switch($modifier_name)
				{
					case 'gt':
						$func = function( $first , $second ) { return ( $first > $second ); };
						break;
					case 'gte':
						$func = function( $first , $second ) { return ( $first >= $second ); };
						break;
					case 'lt':
						$func = function( $first , $second ) { return ( $first < $second ); };
						break;
					case 'lte':
						$func = function( $first , $second ) { return ( $first <= $second ); };
						break;
				}
				if( is_numeric($action1) )
				{
					if( $action3 !== false )
					{
						return function( $input ) use ( $func , $action1 , $action2 , $action3 ) {
							if( is_numeric($input) )
							{
								if( $func( $input , $action1 ) ) { return $action2; }
								else { return $action3; }
							}
							else
							{
								return $input;
							}
						};
					}
					else
					{
						return function( $input ) use ( $func , $action1 , $action2 ) {
							if( is_numeric($input) && $func( $input , $action1 ) ) { return $action2; }
							else { return $input; }
						};
					}
				}
				break;

			case 'heading': // convert to lower case then UPPER CASE first letter in every word
			case 'titleize': // for mySource matrix compatibility
				return function($input) { return ucwords(strtolower($input)); };
				break;

			case 'htmlspecialchars':
			case 'escapehtml':
				return function( $input ) { return htmlspecialchars($input); };
				break;
//			case 'id' see 'csssafe'

			case 'increment':
				return function($input) {
					if( is_numeric($input) )
					{
						return ( $input + 1 );
					}
					return $input;
				};
				break;

			case 'lowercase': // convert string to lower case
				return function($input) { return strtolower($input); };
				break;

			case 'match':
			case 'eq':
			case 'equals':
				if( is_string($action1) )
				{
					if( $action3 !== false )
					{
						return function($input) use ($action1,$action2,$action3) {
							if( $action1 != $input ) { return $action2; }
							else { return $action3; }
						};
					}
					else
					{
						return function($input) use ($action1,$action2) {
							if( $action1 == $input ) { return $action2; }
							else { return $input; }
						};
					}
				}
				break;

			case 'multiply':
				if( is_numeric($action1) )
				{
					$subject = $action1;
					return function($input) use ( $subject ) {
						if( is_numeric($input) )
						{
							return ( $subject * $input );
						}
						return $input;
					};
				}
				break;

//			case 'maxchars': see 'characters'

			case 'maxsentance':
			case 'maxwords':
			case 'words':
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
						$regex = '`^([-\w]+(?:\W+[-\w]+){0,'.$action1.'}).*$`is';
					}
					return function($input) use ($regex) { return preg_replace( $regex , '\1' , $stripcode($input) ); };
				}
				break;

//			case 'nocomment': see 'stripcomment'

			case 'notmatch':
				if( is_string($action1) && $action1 != '' )
				{
					if( $action3 !== false )
					{
						return function($input) use ($action1,$action2,$action3) {
							if( $action1 == $input ) { return $action2; }
							else { return $action3; }
						};
					}
					else
					{
						return function($input) use ($action1,$action2) {
							if( $action1 == $input ) { return $action2; }
							else { return $input; }
						};
					}
				}
				break;

			case 'nomultispace': // make multiple spaces (including lines and tabs) into a single space
			case 'singlespace':
				$output = function($input) { preg_replace('/[\r\n\t ]+/',' ',$lines); };
				break;

			case 'notempty':
				if( $action2 !== false )
				{
					return function($input) use ($action1,$action2) {
						if( !empty($input) ) { return $action1; }
						else { return $action2; }
					};
				}
				else
				{
					return function($input) use ($action1,$action2) {
						if( !empty($input) ) { return $action1; }
						else { return $input; }
					};
				}
				break;


//			case 'plaintext': see 'text'

			case 'pregmatch':
			case 'pregreplace':
			case 'notpregmatch':
				if( is_string($action1) && $action1 != '' )
				{
					@preg_match($action1,'');
					if( preg_last_error() == PREG_NO_ERROR )
					{
						$action1 = $unescape_keyword_specialchars($action1); // unescape keyword special characters
						if( $modifier_name == 'pregmatch' )
						{
							if( $action3 !== false )
							{
								return function($input) use ($action1,$action2,$action3) {
									if( preg_match($action1,$input)) { return $action2; }
									else { return $action3; }
								};
							}
							else
							{
								function($input) use ($action1,$action2) {
									if( preg_match($action1,$input)) { return $action2; }
									else { return $input; }
								};
							}
						}
						elseif( $modifier_name == 'notpregmatch' )
						{
							if( $action3 !== false )
							{
								return function($input) use ($action1,$action2,$action3) {
									if( !preg_match($action1,$input)) { return $action2; }
									else { return $action3; }
								};
							}
							else
							{
								return function($input) use ($action1,$action2) {
									if( !preg_match($action1,$input)) { return $action2; }
									else { return $input; }
								};
							}
						}
						elseif( $modifier_name == 'pregreplace' )
						{
							if( $action2 !== false )
							{
								$action2 = $unescape_keyword_specialchars($action2); // unescape keyword special characters
							}
							else
							{
								$action2 = '';
							}
							return function($input) use ($action1,$action2) {
								return preg_replace($action1,$action2,$input);
							};
						}
					}
				}
				break;

			case 'round':
				if( is_numeric($action1) )
				{
					settype($action1,'float');
				}
				else
				{
					$action1 = 0;
				}
				return function( $input ) use ( $action1 , $modifier_name ) {
					if( is_numeric($input) )
					{
						return round($input,$action1);
					}
					return $input;
				};
				break;


//			case 'singlespace': see 'nomultispace'

			case 'stripcomment': // strips HTML comments
			case 'nocomment':
				return function( $input ) { return preg_replace('`<!--.*?-->|/\*.*?\*/`s','',$input); };
				break;

			case 'striphtml':
				return function( $input ) { return strip_tags($input); };

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
				return function($input) {
					return preg_replace_callback(
						'`(<[a-z]+\s+)([^>]+)(?=>)`i'
						,function($matches) {
							return $matches[1].preg_replace( '`\s+on[a-z]+=(\'|").*?\1`i','',$matches[2]);
						}
						,$input
					);
				};
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
						,array(
							 ''
							,'\1'
						 )
						,$input
					);
				};
				break;

//			case 'plaintext': see 'text'

			case 'sentance': // convert to lower case the uppercase the first character in the string
			case 'capitalize': // for mySource matrix compatibility
				return function($input) {
			       	preg_replace_callback(
						 '`(?<=^|[.?!])(\s+)([a-z])(?=[a-z]*)`i'
						,function( $matches ) {
							return $matches[1].strtoupper($matches[2]);
						}
						,$input
					);
				};
				break;

//			case 'singlespace': see 'nomultispace'

			case 'space': // convert underscores to spaces
				return function($input) { return str_replace('_',' ',$input); };
				break;

			case 'subtract':
				if( is_numeric($action1) )
				{
					$subject = $action1;
					return function($input) use ( $subject ) {
						if( is_numeric($input) )
						{
							return ( $subject - $input );
						}
						return $input;
					};
				}
				break;

			case 'text': // make string plain text
			case 'plaintext':
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

//			case 'titleize': see 'heading'

			case 'trim': // remove white space from begining and end of string
				return function($input) { return trim($input); };
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

//			case 'words': see 'maxwords' & 'maxsentances'

//			case 'words': see maxsentances & maxwords
		}
	}
	return function( $input ) { return $input; };
}

