<?php

/**
 * This is an attempt to write the keyword modifiers process using the functional programming paradigm
 */


function get_add_blank_spacers( $cols , $total_stories , $default_tmpl , $spacer_tmpl , $mode = 'centred' )
{
	if( !is_callable($default_tmpl) )
	{
		// throw $default_tmpl must be a template function;
	}
	if( !is_callable($spacer_tmpl) )
	{
		// throw $spacer_tmpl must be a template function;
	}
	if( is_string($mode) )
	{
		$mode = strtolower($mode);
		switch($mode)
		{
			case 'centered':
				$mode = 'centred';
				break;
			case 'left':
			case 'right':
			case 'centred':
			case 'ballanced':
				break;
			default:
				$mode = 'centred';
		}
	}

	$round_percent = function( $num )
	{
		return ( floor( $num * 1000000 ) / 10000 );
	};

	if( is_int($cols) && is_int($total_stories) && $cols > 1 && $total_stories > 1 )
	{
		$straight = ( $total_stories / $cols );
		if( !is_int($straight) )
		{
			$max = ( ceil($straigh) * $cols );
			$spacers = ( $max - $total_stories );
			$orphans = $cols - $spacers;
			$threshold = $total_stories - $orphans;
			$cell_width = $round_percent( 1 / $cols );
			$spacer_cell_width = $round_percent( ( ( 1 / $cols ) * $spacers ) / 2 );

			if( $spacers > 0 )
			{
				switch( $mode )
				{
					case 'centred':
						$half_space = $spacers / 2;
						$extra_input_arr = array();
						$spacer_arr = array();
						if( !is_int($half_space) )
						{
							$extra_input_arr['colspan'] = ' colspan="2"';
							$extra_input_arr['cell_width'] = ' width="'.$cell_width.'"';
							$spacer_arr['colspan'] = ' colspan="'.$spacers.'"';
							$spacer_arr['cell_width'] = ' width="'.$spacer_cell_width.'"';
						}

						return function( $pos , $input_arr = array() , $custom_tmpl = false ) use ( $default_tmpl , $spacer_tmpl , $spacer_arr , $extra_input_arr , $threshold , $total_stories )
						{
							$tmpl = use_right_tmpl( $custom_tmpl , $default_tmpl );

							$input_arr = array_merge( $input_arr , $extra_input_arr );

							if( $pos == $threshold )
							{
								return $spacer_tmpl($spacer_arr).$tmpl($input_arr);
							}
							elseif( $pos == $total_stories )
							{
								return $tmpl($input_arr).$spacer_tmpl($spacer_arr);
							}
							else
							{
								return $tmpl($input_arr);
							}
						};
						break;

					case 'ballanced':
						// Blank spaces are required to padd out table
						if( $spacers == 2 )
						{
							return function( $pos , $input_arr = array() , $custom_tmpl = false ) use ( $default_tmpl , $spacer_tmpl , $threshold , $total_stories )
							{
								$tmpl = use_right_tmpl( $custom_tmpl , $default_tmpl );

								if( $pos == $threshold )
								{
									return $spacer_tmpl( array() ).$tmpl($input_arr);
								}
								elseif( $pos == $total_stories )
								{
									return $tmpl($input_arr).$spacer_tmpl( array() );
								}
								else
								{
									return $tmpl($input_arr);
								}
							};
						}
						elseif( $spacers == 1 )
						{
							if( preg_match( '`^.*<td[^>]+?colspan`i',$default_tmpl ) )
							{
								// throw error - "To get columns to look good, we need to define a custom colspan for tds to make them all fit. The default template already has a colspan defined. This will break the pattern"
							}
							else
							{
								$spacer_width = ( $cell_width / 2 );
								return function( $pos , $input_arr , $custom_tmpl = false ) use ( $default_tmpl , $spacer_tmpl , $threshold , $total_stories , $cell_width )
								{
									$tmpl = use_right_tmpl( $custom_tmpl , $default_tmpl );
									$input_arr['colspan'] = ' colspan="2"';
									$input_arr['cell_width'] = ' width="'.$cell_width.'%"';

									$spacer_arr = array( 'cell_width' => ' width="'.( $cell_width / 2 ).'%"' );

									if( $pos == $threshold )
									{
										return $spacer_tmpl( $spacer_arr ).$tmpl($input_arr);
									}
									elseif( $pos == $total_stories )
									{
										return $tmpl($input_arr).$spacer_tmpl( $spacer_arr );
									}
									else
									{
										return $tmpl($input_arr);
									}
								};
							}
						}
						//elseif(
						break;
					case 'left':
						return function( $pos , $input_arr = array() , $custom_tmpl = false ) use ( $default_tmpl , $spacer_tmpl , $total_stories , $spacers )
						{
							$tmpl = use_right_tmpl( $custom_tmpl , $default_tmpl );
							if( $pos == $total_stories )
							{
								$output = $tmpl($input_arr);
								for( $a = 0 ; $a < $spacers ; $a += 1 )
								{
									$output .= $spacer_tmpl();
								}
								return $output;
							}
							else
							{
								return $tmpl( $input_arr );
							}
						};
						break;

					case 'right':
						return function( $pos , $input_arr = array() , $custom_tmpl = false ) use ( $default_tmpl , $spacer_tmpl , $threshold , $spacers)
						{
							$tmpl = use_right_tmpl( $custom_tmpl , $default_tmpl );
							if( $pos == $threshold )
							{
								$output = $spacer_tmpl();
								for( $a = 1 ; $a < $spacers ; $a += 1 )
								{
									$output .= $spacer_tmpl();
								}
								return $output.$tmpl($input_arr);
							}
							else
							{
								return $tmpl($input_arr);
							}
						};
				}
			}
		}
	}
	// no blank spacers required
	return function( $tmpl , $pos ) { return $tmpl; };
}

function use_right_tmpl( $test_tmpl , $default_tmpl )
{
	if( is_callable($test_tmpl) )
	{
		return $test_tmpl;
	}
	else
	{
		return $default_tmpl;
	}
}
