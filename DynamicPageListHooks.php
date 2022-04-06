<?php
/**
 *
 * @file
 * @ingroup Extensions
 * @link		http://www.mediawiki.org/wiki/Extension:DynamicPageList_(third-party)	Documentation
 * @author		n:en:User:IlyaHaykinson
 * @author		n:en:User:Amgine
 * @author		w:de:Benutzer:Unendlich
 * @author		m:User:Dangerman <cyril.dangerville@gmail.com>
 * @author		m:User:Algorithmix <gero.scholz@gmx.de>
 * @license		GPL-2.0-or-later
 *
 */

class DynamicPageListHooks {
	// FATAL
	public const FATAL_WRONGNS = 1001;	// $1: 'namespace' or 'notnamespace'
															// $2: wrong parameter given by user
															// $3: list of possible titles of namespaces (except pseudo-namespaces: Media, Special)

	public const FATAL_WRONGLINKSTO = 1002;	// $1: linksto'
															// $2: the wrong parameter given by user

	public const FATAL_TOOMANYCATS = 1003;	// $1: max number of categories that can be included

	public const FATAL_TOOFEWCATS = 1004;	// $1: min number of categories that have to be included

	public const FATAL_NOSELECTION = 1005;

	public const FATAL_CATDATEBUTNOINCLUDEDCATS = 1006;

	public const FATAL_CATDATEBUTMORETHAN1CAT = 1007;

	public const FATAL_MORETHAN1TYPEOFDATE = 1008;

	public const FATAL_WRONGORDERMETHOD = 1009;	// $1: param=val that is possible only with $1 as last 'ordermethod' parameter
															// $2: last 'ordermethod' parameter required for $0

	public const FATAL_DOMINANTSECTIONRANGE = 1010;	// $1: the number of arguments in includepage

	public const FATAL_OPENREFERENCES = 1012;

	public const FATAL_MISSINGPARAMFUNCTION = 1022;

	public const FATAL_NOTPROTECTED = 1023;

	public const FATAL_SQLBUILDERROR = 1024;

	// ERROR

	// WARN

	public const WARN_UNKNOWNPARAM = 2013;	// $1: unknown parameter given by user
															// $2: list of DPL available parameters separated by ', '

	public const WARN_PARAMNOOPTION = 2022;	// $1: Parameter given by user

	public const WARN_WRONGPARAM = 2014;	// $3: list of valid param values separated by ' | '

	public const WARN_WRONGPARAM_INT = 2015;	// $1: param name
															// $2: wrong param value given by user
															// $3: default param value used instead by program

	public const WARN_NORESULTS = 2016;

	public const WARN_CATOUTPUTBUTWRONGPARAMS = 2017;

	public const WARN_HEADINGBUTSIMPLEORDERMETHOD = 2018;	// $1: 'headingmode' value given by user
															// $2: value used instead by program (which means no heading)

	public const WARN_DEBUGPARAMNOTFIRST = 2019;	// $1: 'log' value

	public const WARN_TRANSCLUSIONLOOP = 2020;	// $1: title of page that creates an infinite transclusion loop

	public const DEBUG_QUERY = 3021;	// $1: SQL query executed to generate the dynamic page list

	/**
	 * @var string[]
	 */
	public static $fixedCategories = [];

	/**
	 * @var string[]
	 */
	public static $createdLinks; // the links created by DPL are collected here;
								 // they can be removed during the final ouput
								 // phase of the MediaWiki parser

	/**
	 * DPL acting like Extension:Intersection
	 *
	 * @var bool
	 */
	private static $likeIntersection = false;

	/**
	 * Debugging Level
	 *
	 * @var int
	 */
	private static $debugLevel = 0;

	/**
	 * Handle special on extension registration bits.
	 *
	 * @access	public
	 * @return	void
	 */
	public static function onRegistration() {
		if ( !defined( 'DPL_VERSION' ) ) {
			define( 'DPL_VERSION', '3.3.3' );
		}
	}

	/**
	 * Sets up this extension's parser functions.
	 *
	 * @access	public
	 * @param	object	Parser object passed as a reference.
	 * @return bool true
	 */
	public static function onParserFirstCallInit( Parser &$parser ) {
		self::init();

		// DPL offers the same functionality as Intersection.  So we register the <DynamicPageList> tag in case LabeledSection Extension is not installed so that the section markers are removed.
		if ( \DPL\Config::getSetting( 'handleSectionTag' ) ) {
			$parser->setHook( 'section',			[ __CLASS__, 'dplTag' ] );
		}
		$parser->setHook( 'DPL',					[ __CLASS__, 'dplTag' ] );
		$parser->setHook( 'DynamicPageList',		[ __CLASS__, 'intersectionTag' ] );

		$parser->setFunctionHook( 'dpl',			[ __CLASS__, 'dplParserFunction' ] );
		$parser->setFunctionHook( 'dplnum',		[ __CLASS__, 'dplNumParserFunction' ] );
		$parser->setFunctionHook( 'dplvar',		[ __CLASS__, 'dplVarParserFunction' ] );
		$parser->setFunctionHook( 'dplreplace',	[ __CLASS__, 'dplReplaceParserFunction' ] );
		$parser->setFunctionHook( 'dplchapter',	[ __CLASS__, 'dplChapterParserFunction' ] );
		$parser->setFunctionHook( 'dplmatrix',	[ __CLASS__, 'dplMatrixParserFunction' ] );

		return true;
	}

	/**
	 * Sets up this extension's parser functions for migration from Intersection.
	 *
	 * @access	public
	 * @param	object	Parser object passed as a reference.
	 * @return bool true
	 */
	public static function setupMigration( Parser &$parser ) {
		$parser->setHook( 'Intersection', [ __CLASS__, 'intersectionTag' ] );
		$parser->addTrackingCategory( 'dpl-intersection-tracking-category' );

		self::init();

		return true;
	}

	/**
	 * Common initializer for usage from parser entry points.
	 *
	 * @private
	 * @return	void
	 */
	private static function init() {
		\DPL\Config::init();

		if ( !isset( self::$createdLinks ) ) {
			self::$createdLinks = [
				'resetLinks'		=> false,
				'resetTemplates'	=> false,
				'resetCategories'	=> false,
				'resetImages'		=> false,
				'resetdone'			=> false,
				'elimdone'			=> false
			];
		}
	}

	/**
	 * Set to behave like intersection.
	 *
	 * @private
	 * @param	boolean	Behave Like Intersection
	 * @return	void
	 */
	private static function setLikeIntersection( $mode = false ) {
		self::$likeIntersection = $mode;
	}

	/**
	 * Is like intersection?
	 *
	 * @access	public
	 * @return bool Behaving Like Intersection
	 */
	public static function isLikeIntersection() {
		return (bool)self::$likeIntersection;
	}

	/**
	 * Tag <section> entry point.
	 *
	 * @access	public
	 * @param	string	Raw User Input
	 * @param	array	Arguments on the tag.
	 * @param	object	Parser object.
	 * @param	object	PPFrame object.
	 * @return string HTML
	 */
	public static function intersectionTag( $input, array $args, Parser $parser, PPFrame $frame ) {
		self::setLikeIntersection( true );
		return self::executeTag( $input, $args, $parser, $frame );
	}

	/**
	 * Tag <dpl> entry point.
	 *
	 * @access	public
	 * @param	string	Raw User Input
	 * @param	array	Arguments on the tag.
	 * @param	object	Parser object.
	 * @param	object	PPFrame object.
	 * @return string HTML
	 */
	public static function dplTag( $input, array $args, Parser $parser, PPFrame $frame ) {
		self::setLikeIntersection( false );
		$parser->addTrackingCategory( 'dpl-tag-tracking-category' );
		return self::executeTag( $input, $args, $parser, $frame );
	}

	/**
	 * The callback function wrapper for converting the input text to HTML output
	 *
	 * @access	public
	 * @param	string	Raw User Input
	 * @param	array	Arguments on the tag.(While not used, it is left here for future compatibility.)
	 * @param	object	Parser object.
	 * @param	object	PPFrame object.
	 * @return string HTML
	 */
	private static function executeTag( $input, array $args, Parser $parser, PPFrame $frame ) {
		// entry point for user tag <dpl>  or  <DynamicPageList>
		// create list and do a recursive parse of the output

		$parse = new \DPL\Parse();
		if ( \DPL\Config::getSetting( 'recursiveTagParse' ) ) {
			$input = $parser->recursiveTagParse( $input, $frame );
		}
		$text = $parse->parse( $input, $parser, $reset, $eliminate, true );

		if ( isset( $reset['templates'] ) && $reset['templates'] ) {	// we can remove the templates by save/restore
			$saveTemplates = $parser->getOutput()->mTemplates;
		}
		if ( isset( $reset['categories'] ) && $reset['categories'] ) {	// we can remove the categories by save/restore
			$saveCategories = $parser->getOutput()->mCategories;
		}
		if ( isset( $reset['images'] ) && $reset['images'] ) {	// we can remove the images by save/restore
			$saveImages = $parser->getOutput()->mImages;
		}
		$parsedDPL = $parser->recursiveTagParse( $text );
		if ( isset( $reset['templates'] ) && $reset['templates'] ) {
			$parser->getOutput()->mTemplates = $saveTemplates;
		}
		if ( isset( $reset['categories'] ) && $reset['categories'] ) {
			$parser->getOutput()->mCategories = $saveCategories;
		}
		if ( isset( $reset['images'] ) && $reset['images'] ) {
			$parser->getOutput()->mImages = $saveImages;
		}

		return $parsedDPL;
	}

	/**
	 * The #dpl parser tag entry point.
	 *
	 * @access	public
	 * @param	object	Parser object passed as a reference.
	 * @return string Wiki Text
	 */
	public static function dplParserFunction( &$parser ) {
		self::setLikeIntersection( false );

		$parser->addTrackingCategory( 'dpl-parserfunc-tracking-category' );

		// callback for the parser function {{#dpl:	  or   {{DynamicPageList::
		$input = "";

		$numargs = func_num_args();
		if ( $numargs < 2 ) {
			$input = "#dpl: no arguments specified";
			return str_replace( '§', '<', '§pre>§nowiki>' . $input . '§/nowiki>§/pre>' );
		}

		// fetch all user-provided arguments (skipping $parser)
		$arg_list = func_get_args();
		for ( $i = 1; $i < $numargs; $i++ ) {
			$p1 = $arg_list[$i];
			$input .= str_replace( "\n", "", $p1 ) . "\n";
		}

		$parse = new \DPL\Parse();
		$dplresult = $parse->parse( $input, $parser, $reset, $eliminate, false );
		return [ // parser needs to be coaxed to do further recursive processing
			$parser->getPreprocessor()->preprocessToObj( $dplresult, Preprocessor::DOM_FOR_INCLUSION ),
			'isLocalObj' => true,
			'title' => $parser->getTitle()
		];
	}

	/**
	 * The #dplnum parser tag entry point.
	 * From the old documentation: "Tries to guess a number that is buried in the text.  Uses a set of heuristic rules which may work or not.  The idea is to extract the number so that it can be used as a sorting value in the column of a DPL table output."
	 *
	 * @access	public
	 * @param	object	Parser object passed as a reference.
	 * @return string Wiki Text
	 */
	public static function dplNumParserFunction( &$parser, $text = '' ) {
		$parser->addTrackingCategory( 'dplnum-parserfunc-tracking-category' );
		$num = str_replace( '&#160;', ' ', $text );
		$num = str_replace( '&nbsp;', ' ', $text );
		$num = preg_replace( '/([0-9])([.])([0-9][0-9]?[^0-9,])/', '\1,\3', $num );
		$num = preg_replace( '/([0-9.]+),([0-9][0-9][0-9])\s*Mrd/', '\1\2 000000 ', $num );
		$num = preg_replace( '/([0-9.]+),([0-9][0-9])\s*Mrd/', '\1\2 0000000 ', $num );
		$num = preg_replace( '/([0-9.]+),([0-9])\s*Mrd/', '\1\2 00000000 ', $num );
		$num = preg_replace( '/\s*Mrd/', '000000000 ', $num );
		$num = preg_replace( '/([0-9.]+),([0-9][0-9][0-9])\s*Mio/', '\1\2 000 ', $num );
		$num = preg_replace( '/([0-9.]+),([0-9][0-9])\s*Mio/', '\1\2 0000 ', $num );
		$num = preg_replace( '/([0-9.]+),([0-9])\s*Mio/', '\1\2 00000 ', $num );
		$num = preg_replace( '/\s*Mio/', '000000 ', $num );
		$num = preg_replace( '/[. ]/', '', $num );
		$num = preg_replace( '/^[^0-9]+/', '', $num );
		$num = preg_replace( '/[^0-9].*/', '', $num );
		return $num;
	}

	public static function dplVarParserFunction( &$parser, $cmd ) {
		$parser->addTrackingCategory( 'dplvar-parserfunc-tracking-category' );
		$args = func_get_args();
		if ( $cmd == 'set' ) {
			return \DPL\Variables::setVar( $args );
		} elseif ( $cmd == 'default' ) {
			return \DPL\Variables::setVarDefault( $args );
		}
		return \DPL\Variables::getVar( $cmd );
	}

	private static function isRegexp( $needle ) {
		if ( strlen( $needle ) < 3 ) {
			return false;
		}
		if ( ctype_alnum( $needle[0] ) ) {
			return false;
		}
		$nettoNeedle = preg_replace( '/[ismu]*$/', '', $needle );
		if ( strlen( $nettoNeedle ) < 2 ) {
			return false;
		}
		if ( $needle[0] == $nettoNeedle[strlen( $nettoNeedle ) - 1] ) {
			return true;
		}
		return false;
	}

	public static function dplReplaceParserFunction( &$parser, $text, $pat = '', $repl = '' ) {
		$parser->addTrackingCategory( 'dplreplace-parserfunc-tracking-category' );
		if ( $text == '' || $pat == '' ) {
			return '';
		}
		# convert \n to a real newline character
		$repl = str_replace( '\n', "\n", $repl );

		# replace
		if ( !self::isRegexp( $pat ) ) {
			$pat = '`' . str_replace( '`', '\`', $pat ) . '`';
		}

		return @preg_replace( $pat, $repl, $text );
	}

	public static function dplChapterParserFunction( &$parser, $text = '', $heading = ' ', $maxLength = -1, $page = '?page?', $link = 'default', $trim = false ) {
		$parser->addTrackingCategory( 'dplchapter-parserfunc-tracking-category' );
		$output = \DPL\LST::extractHeadingFromText( $parser, $page, '?title?', $text, $heading, '', $sectionHeading, true, $maxLength, $link, $trim );
		return $output[0];
	}

	public static function dplMatrixParserFunction( &$parser, $name = '', $yes = '', $no = '', $flip = '', $matrix = '' ) {
		$parser->addTrackingCategory( 'dplmatrix-parserfunc-tracking-category' );
		$lines   = explode( "\n", $matrix );
		$m       = [];
		$sources = [];
		$targets = [];
		$from    = '';
		$to      = '';
		if ( $flip == '' | $flip == 'normal' ) {
			$flip = false;
		} else {
			$flip = true;
		}
		if ( $name == '' ) {
			$name = '&#160;';
		}
		if ( $yes == '' ) {
			$yes = ' x ';
		}
		if ( $no == '' ) {
			$no = '&#160;';
		}
		if ( $no[0] == '-' ) {
			$no = " $no ";
		}
		foreach ( $lines as $line ) {
			if ( strlen( $line ) <= 0 ) {
				continue;
			}
			if ( $line[0] != ' ' ) {
				$from = preg_split( ' *\~\~ *', trim( $line ), 2 );
				if ( !array_key_exists( $from[0], $sources ) ) {
					if ( count( $from ) < 2 || $from[1] == '' ) {
						$sources[$from[0]] = $from[0];
					} else {
						$sources[$from[0]] = $from[1];
					}
					$m[$from[0]] = [];
				}
			} elseif ( trim( $line ) != '' ) {
				$to = preg_split( ' *\~\~ *', trim( $line ), 2 );
				if ( count( $to ) < 2 || $to[1] == '' ) {
					$targets[$to[0]] = $to[0];
				} else {
					$targets[$to[0]] = $to[1];
				}
				$m[$from[0]][$to[0]] = true;
			}
		}
		ksort( $targets );

		$header = "\n";

		if ( $flip ) {
			foreach ( $sources as $from => $fromName ) {
				$header .= "![[$from|" . $fromName . "]]\n";
			}
			foreach ( $targets as $to => $toName ) {
				$targets[$to] = "[[$to|$toName]]";
				foreach ( $sources as $from => $fromName ) {
					if ( array_key_exists( $to, $m[$from] ) ) {
						$targets[$to] .= "\n|$yes";
					} else {
						$targets[$to] .= "\n|$no";
					}
				}
				$targets[$to] .= "\n|--\n";
			}
			return "{|class=dplmatrix\n|$name" . "\n" . $header . "|--\n!" . implode( "\n!", $targets ) . "\n|}";
		} else {
			foreach ( $targets as $to => $toName ) {
				$header .= "![[$to|" . $toName . "]]\n";
			}
			foreach ( $sources as $from => $fromName ) {
				$sources[$from] = "[[$from|$fromName]]";
				foreach ( $targets as $to => $toName ) {
					if ( array_key_exists( $to, $m[$from] ) ) {
						$sources[$from] .= "\n|$yes";
					} else {
						$sources[$from] .= "\n|$no";
					}
				}
				$sources[$from] .= "\n|--\n";
			}
			return "{|class=dplmatrix\n|$name" . "\n" . $header . "|--\n!" . implode( "\n!", $sources ) . "\n|}";
		}
	}

	// remove section markers in case the LabeledSectionTransclusion extension is not installed.
	public static function removeSectionMarkers( $in, $assocArgs = [], $parser = null ) {
		return '';
	}

	public static function fixCategory( $cat ) {
		if ( $cat != '' ) {
			self::$fixedCategories[$cat] = 1;
		}
	}

	/**
	 * Set Debugging Level
	 *
	 * @access	public
	 * @param	integer	Debug Level
	 * @return	void
	 */
	public static function setDebugLevel( $level ) {
		self::$debugLevel = intval( $level );
	}

	/**
	 * Return Debugging Level
	 *
	 * @access	public
	 * @return void
	 */
	public static function getDebugLevel() {
		return self::$debugLevel;
	}

	// reset everything; some categories may have been fixed, however via  fixcategory=
	public static function endReset( &$parser, $text ) {
		if ( !self::$createdLinks['resetdone'] ) {
			self::$createdLinks['resetdone'] = true;
			foreach ( $parser->getOutput()->mCategories as $key => $val ) {
				if ( array_key_exists( $key, self::$fixedCategories ) ) {
					self::$fixedCategories[$key] = $val;
				}
			}
			// $text .= self::dumpParsedRefs($parser,"before final reset");
			if ( self::$createdLinks['resetLinks'] ) {
				$parser->getOutput()->mLinks = [];
			}
			if ( self::$createdLinks['resetCategories'] ) {
				$parser->getOutput()->mCategories = self::$fixedCategories;
			}
			if ( self::$createdLinks['resetTemplates'] ) {
				$parser->getOutput()->mTemplates = [];
			}
			if ( self::$createdLinks['resetImages'] ) {
				$parser->getOutput()->mImages = [];
			}
			// $text .= self::dumpParsedRefs($parser,"after final reset");
			self::$fixedCategories = [];
		}
		return true;
	}

	public static function endEliminate( &$parser, &$text ) {
		// called during the final output phase; removes links created by DPL
		if ( isset( self::$createdLinks ) ) {
			// self::dumpParsedRefs($parser,"before final eliminate");
			if ( array_key_exists( 0, self::$createdLinks ) ) {
				foreach ( $parser->getOutput()->getLinks() as $nsp => $link ) {
					if ( !array_key_exists( $nsp, self::$createdLinks[0] ) ) {
						continue;
					}
					// echo ("<pre> elim: created Links [$nsp] = ". count(DynamicPageListHooks::$createdLinks[0][$nsp])."</pre>\n");
					// echo ("<pre> elim: parser  Links [$nsp] = ". count($parser->getOutput()->mLinks[$nsp])			 ."</pre>\n");
					$parser->getOutput()->mLinks[$nsp] = array_diff_assoc( $parser->getOutput()->mLinks[$nsp], self::$createdLinks[0][$nsp] );
					// echo ("<pre> elim: parser  Links [$nsp] nachher = ". count($parser->getOutput()->mLinks[$nsp])	  ."</pre>\n");
					if ( count( $parser->getOutput()->mLinks[$nsp] ) == 0 ) {
						unset( $parser->getOutput()->mLinks[$nsp] );
					}
				}
			}
			if ( isset( self::$createdLinks ) && array_key_exists( 1, self::$createdLinks ) ) {
				foreach ( $parser->getOutput()->mTemplates as $nsp => $tpl ) {
					if ( !array_key_exists( $nsp, self::$createdLinks[1] ) ) {
						continue;
					}
					// echo ("<pre> elim: created Tpls [$nsp] = ". count(DynamicPageListHooks::$createdLinks[1][$nsp])."</pre>\n");
					// echo ("<pre> elim: parser  Tpls [$nsp] = ". count($parser->getOutput()->mTemplates[$nsp])			."</pre>\n");
					$parser->getOutput()->mTemplates[$nsp] = array_diff_assoc( $parser->getOutput()->mTemplates[$nsp], self::$createdLinks[1][$nsp] );
					// echo ("<pre> elim: parser  Tpls [$nsp] nachher = ". count($parser->getOutput()->mTemplates[$nsp])	 ."</pre>\n");
					if ( count( $parser->getOutput()->mTemplates[$nsp] ) == 0 ) {
						unset( $parser->getOutput()->mTemplates[$nsp] );
					}
				}
			}
			if ( isset( self::$createdLinks ) && array_key_exists( 2, self::$createdLinks ) ) {
				$parser->getOutput()->mCategories = array_diff_assoc( $parser->getOutput()->mCategories, self::$createdLinks[2] );
			}
			if ( isset( self::$createdLinks ) && array_key_exists( 3, self::$createdLinks ) ) {
				$parser->getOutput()->mImages = array_diff_assoc( $parser->getOutput()->mImages, self::$createdLinks[3] );
			}
			// $text .= self::dumpParsedRefs($parser,"after final eliminate".$parser->mTitle->getText());
		}

		// self::$createdLinks=array(
		//		  'resetLinks'=> false, 'resetTemplates' => false,
		//		  'resetCategories' => false, 'resetImages' => false, 'resetdone' => false );
		return true;
	}

	/**
	 * Setups and Modifies Database Information
	 *
	 * @access	public
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$extDir = __DIR__;

		$updater->addPostDatabaseUpdateMaintenance( 'DPL\\DB\\CreateTemplateUpdateMaintenance' );

		$db = $updater->getDB();
		if ( !$db->tableExists( 'dpl_clview' ) ) {
			// PostgreSQL doesn't have IFNULL, so use COALESCE instead
			$sqlNullMethod = ( $db->getType() === 'postgres' ? 'COALESCE' : 'IFNULL' );
			$db->query( "CREATE VIEW {$db->tablePrefix()}dpl_clview AS SELECT $sqlNullMethod(cl_from, page_id) AS cl_from, $sqlNullMethod(cl_to, '') AS cl_to, cl_sortkey FROM {$db->tablePrefix()}page LEFT OUTER JOIN {$db->tablePrefix()}categorylinks ON {$db->tablePrefix()}page.page_id=cl_from;" );
		}
	}
}
