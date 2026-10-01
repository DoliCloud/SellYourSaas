<?php

define('DOL_PROJECT_ROOT', __DIR__.'/../../..');
define('DOL_DOCUMENT_ROOT', DOL_PROJECT_ROOT.'/htdocs');
//define('DOL_DOCUMENT_ROOT', DOL_PROJECT_ROOT);
define('PHAN_DIR', __DIR__);

/**
 * This configuration will be read and overlaid on top of the
 * default configuration. Command line arguments will be applied
 * after this file is read.
 */
return [
	//    'processes' => 6,
	'backward_compatibility_checks' => false,
	'simplify_ast'=>true,
	'analyzed_file_extensions' => ['php','inc'],

	// Supported values: `'5.6'`, `'7.0'`, `'7.1'`, `'7.2'`, `'7.3'`, `'7.4'`, `null`.
	// If this is set to `null`,
	// then Phan assumes the PHP version which is closest to the minor version
	// of the php executable used to execute Phan.
	//"target_php_version" => null,
	//"target_php_version" => '7.0',
	//"target_php_version" => '7.4',
	//"target_php_version" => '7.3',
	//"target_php_version" => '5.6',
	"target_php_version" => '8.2',
	'minimum-target-php-version'=>'7.0',

	// A list of directories that should be parsed for class and
	// method information. After excluding the directories
	// defined in exclude_analysis_directory_list, the remaining
	// files will be statically analyzed for errors.
	//
	// Thus, both first-party and third-party code being used by
	// your application should be included in this list.
	'directory_list' => [
		'htdocs',
		'dev/tools/phan/stubs',
		//'tests',
	],

	// A directory list that defines files that will be excluded
	// from static analysis, but whose class and method
	// information should be included.
	//
	// Generally, you'll want to include the directories for
	// third-party code (such as "vendor/") in this list.
	//
	// n.b.: If you'd like to parse but not analyze 3rd
	//       party code, directories containing that code
	//       should be added to the `directory_list` as
	//       to `exclude_analysis_directory_list`.
	"exclude_analysis_directory_list" => [
		'dev/tools/phan/stubs',
	],
	//'exclude_file_regex' => '@^vendor/.*/(tests?|Tests?)/@',
	'exclude_file_regex' => '@^('
		.'nonexisting'
		.'|htdocs/myaccount/source/.*'
		.')$@',



	// A list of plugin files to execute.
	// Plugins which are bundled with Phan can be added here by providing their name
	// (e.g. 'AlwaysReturnPlugin')
	//
	// Documentation about available bundled plugins can be found
	// at https://github.com/phan/phan/tree/master/dev/tools/phan/plugins
	//
	// Alternately, you can pass in the full path to a PHP file
	// with the plugin's implementation (e.g. 'vendor/phan/phan/dev/tools/phan/plugins/AlwaysReturnPlugin.php')
	'plugins' => [
		__DIR__.'/plugins/SqlInjectionPlugin.php',
		// checks if a function, closure or method unconditionally returns.
		// can also be written as 'vendor/phan/phan/dev/tools/phan/plugins/AlwaysReturnPlugin.php'
		'DeprecateAliasPlugin',
		'EmptyMethodAndFunctionPlugin',
		'InvalidVariableIssetPlugin',
		'MoreSpecificElementTypePlugin',
		'NoAssertPlugin',
		'NotFullyQualifiedUsagePlugin',
		'PHPDocRedundantPlugin',
		'PHPUnitNotDeadCodePlugin',
		'PossiblyStaticMethodPlugin',
		'PreferNamespaceUsePlugin',
		'PrintfCheckerPlugin',
		'RedundantAssignmentPlugin',
	// PhanPluginCanUseParamType : 1300+ occurrences
	// PhanPluginComparisonNotStrictForScalar : 700+ occurrences
	// PhanPluginCanUseReturnType : 680+ occurrences
	// PhanPluginNumericalComparison : 470+ occurrences
	// PhanPluginNonBoolInLogicalArith : 290+ occurrences
	// PhanPluginPossiblyStaticClosure : 270+ occurrences
	// PhanPluginPossiblyStaticPublicMethod : 230+ occurrences
	// PhanPluginSuspiciousParamPosition : 150+ occurrences
	// PhanPluginCanUseNullableParamType : 140+ occurrences
	// PhanPluginCanUsePHP71Void : 130+ occurrences
	// PhanPluginInlineHTML : 100+ occurrences
	// PhanPluginPossiblyStaticPrivateMethod : 100+ occurrences
	// PhanPluginCanUseNullableReturnType : 90+ occurrences
	// PhanPluginInlineHTMLTrailing : 65+ occurrences

	/* Could be enabled for new code.
		'ConstantVariablePlugin', // Warns about values that are actually constant
		'HasPHPDocPlugin', // Requires PHPDoc
		'InlineHTMLPlugin', // html in PHP file, or at end of file
		'NonBoolBranchPlugin', // Requires test on bool, nont on ints
		'NonBoolInLogicalArithPlugin',
		'NumericalComparisonPlugin',
		'PHPDocToRealTypesPlugin',
		'ShortArrayPlugin', // Checks that [] is used
		'StrictLiteralComparisonPlugin',
		'UnknownClassElementAccessPlugin',
		'UnknownElementTypePlugin',
		'WhitespacePlugin',
	/**/
		'PHPDocInWrongCommentPlugin', // Missing /** (/* was used)
		//'RemoveDebugStatementPlugin', // Reports echo, print, ...
		'SimplifyExpressionPlugin',
		//'StrictComparisonPlugin', // Expects ===
		//'SuspiciousParamOrderPlugin', // reports function calls for parameters, not clear
		'UnsafeCodePlugin',
		//'UnusedSuppressionPlugin',

		'AlwaysReturnPlugin',
		//'DollarDollarPlugin',
		'DuplicateArrayKeyPlugin',
		'DuplicateExpressionPlugin',
		'PregRegexCheckerPlugin',
		'PrintfCheckerPlugin',
		'SleepCheckerPlugin',
		// Checks for syntactically unreachable statements in
		// the global scope or function bodies.
		'UnreachableCodePlugin',
		'UseReturnValuePlugin',
		'EmptyStatementListPlugin',
		'LoopVariableReusePlugin',
	],

	// Add any issue types (such as 'PhanUndeclaredMethod')
	// here to inhibit them from being reported
	'suppress_issue_types' => [
		'PhanTypeMismatchReturnSuperType', // Newly introduced in phan many occurrences.
		//'PhanUndeclaredThis',
	'PhanPluginMixedKeyNoKey',
	'PhanPluginDuplicateConditionalNullCoalescing', // Suggests to optimize to ??
		//'PhanUnreferencedClosure',  // False positives seen with closures in arrays, TODO: move closure checks closer to what is done by unused variable plugin
		//'PhanPluginNoCommentOnProtectedMethod',
		//'PhanPluginDescriptionlessCommentOnProtectedMethod',
		//'PhanPluginNoCommentOnPrivateMethod',
		//'PhanPluginDescriptionlessCommentOnPrivateMethod',
		//'PhanPluginDescriptionlessCommentOnPrivateProperty',
		// TODO: Fix edge cases in --automatic-fix for PhanPluginRedundantClosureComment
		//'PhanPluginRedundantClosureComment',
		'PhanPluginPossiblyStaticPublicMethod',
		//'PhanPluginPossiblyStaticProtectedMethod',

		// The types of ast\Node->children are all possibly unset.
		'PhanTypePossiblyInvalidDimOffset', // Also checks optional array keys and requires that they are checked for existence.
	],
	// You can put relative paths to internal stubs in this config option.
	// Phan will continue using its detailed type annotations,
	// but load the constants, classes, functions, and classes (and their Reflection types)
	// from these stub files (doubling as valid php files).
	// Use a different extension from php (and preferably a separate folder)
	// to avoid accidentally parsing these as PHP (includes projects depending on this).
	// The 'mkstubs' script can be used to generate your own stubs (compatible with php 7.0+ right now)
	// Note: The array key must be the same as the extension name reported by `php -m`,
	// so that phan can skip loading the stubs if the extension is actually available.
	'autoload_internal_extension_signatures' => [
		 // Xdebug stubs are bundled with Phan 0.10.1+/0.8.9+ for usage,
		 // because Phan disables xdebug by default.
		 //'xdebug'     => 'vendor/phan/phan/dev/tools/phan/internal_stubs/xdebug.phan_php',
		//'memcached'  => 'dev/tools/phan/your_internal_stubs_folder_name/memcached.phan_php',
		'curl'  => 'dev/tools/phan/stubs/curl.phan_php',
		'gd'  => 'dev/tools/phan/stubs/gd.phan_php',
		'intl'  => 'dev/tools/phan/stubs/intl.phan_php',
		'mcrypt'  => 'dev/tools/phan/stubs/mcrypt.phan_php',
		'soap'  => 'dev/tools/phan/stubs/soap.phan_php',
		'pdo_mysql'  => 'dev/tools/phan/stubs/pdo_mysql.phan_php',
		'PDO'  => 'dev/tools/phan/stubs/PDO.phan_php',
		'zip'  => 'dev/tools/phan/stubs/zip.phan_php',
		'SimpleXML'  => 'dev/tools/phan/stubs/SimpleXML.phan_php',
	],
];
