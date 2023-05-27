#!/usr/bin/env php
<?php
//========================================================================
// Author:  Pascal KISSIAN
// Resume:  http://pascal.kissian.net
//
// Copyright (c) 2015-2020 Pascal KISSIAN
//
// Published under the MIT License
//          Consider it as a proof of concept!
//          No warranty of any kind.
//          Use and abuse at your own risks.
//========================================================================
if (isset($_SERVER["SERVER_SOFTWARE"]) && ($_SERVER["SERVER_SOFTWARE"]!="") ){ echo "<h1>Comand Line Interface Only!</h1>"; die; }


const PHP_PARSER_DIRECTORY  = 'php-parser';


require_once 'include/check_version.php';

require_once 'include/get_default_defined_objects.php';     // include this file before defining something....


require_once 'include/classes/config.php';
require_once 'include/classes/scrambler.php';
require_once 'include/functions.php';
require_once 'version.php';

include      'include/retrieve_config_and_arguments.php';

require_once 'include/classes/parser_extensions/my_autoloader.php';
require_once 'include/classes/parser_extensions/my_pretty_printer.php';
require_once 'include/classes/parser_extensions/my_node_visitor.php';


//if ($clean_mode && file_exists("$target_directory/yakpro-po/.yakpro-po-directory") )
//{
//    if (!$conf->silent) fprintf(STDERR,"Info:\tRemoving directory\t= [%s]%s","$target_directory/yakpro-po",PHP_EOL);
//    remove_directory("$target_directory/yakpro-po");
//    exit(31);
//}

use PhpParser\Error;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\PrettyPrinter;

switch($conf->parser_mode)
{
    case 'PREFER_PHP7': $parser_mode = ParserFactory::PREFER_PHP7;  break;
    case 'PREFER_PHP5': $parser_mode = ParserFactory::PREFER_PHP5;  break;
    case 'ONLY_PHP7':   $parser_mode = ParserFactory::ONLY_PHP7;    break;
    case 'ONLY_PHP5':   $parser_mode = ParserFactory::ONLY_PHP5;    break;
    default:            $parser_mode = ParserFactory::PREFER_PHP5;  break;
}

$parser = (new ParserFactory)->create($parser_mode);


$traverser          = new NodeTraverser;

if ($conf->obfuscate_string_literal)    $prettyPrinter      = new myPrettyprinter;
else                                    $prettyPrinter      = new PrettyPrinter\Standard;

$t_scrambler = array();
//foreach(array('variable','function','method','property','class','class_constant','constant','label') as $scramble_what)
foreach(array('variable','function_or_class','method','property','class_constant','constant','label') as $scramble_what)
{
    $t_scrambler[$scramble_what] = new Scrambler($scramble_what, $conf, ($process_mode=='directory') ? $target_directory : null);
}
if ($whatis!=='')
{
    if ($whatis[0] == '$') $whatis = substr($whatis,1);
//    foreach(array('variable','function','method','property','class','class_constant','constant','label') as $scramble_what)
    foreach(array('variable','function_or_class','method','property','class_constant','constant','label') as $scramble_what)
    {
        if ( ( $s = $t_scrambler[$scramble_what]-> unscramble($whatis)) !== '')
        {
            switch($scramble_what)
            {
                case 'variable':
                case 'property':
                    $prefix = '$';
                    break;
                default:
                    $prefix = '';
            }
            echo "$scramble_what: {$prefix}{$s}".PHP_EOL;
        }
    }
    exit(32);
}

$traverser->addVisitor(new MyNodeVisitor);

switch($process_mode)
{
    case 'file':
        $obfuscated_str =  obfuscate($source_file);
        if ($obfuscated_str===null) { exit(33);                                       }
        if ($target_file   ===''  ) { echo $obfuscated_str.PHP_EOL.PHP_EOL; exit(34); }
        file_put_contents($target_file,$obfuscated_str);
        exit(0);
    case 'directory':
        if (isset($conf->t_skip) && is_array($conf->t_skip)) foreach($conf->t_skip as $key=>$val) $conf->t_skip[$key] = "$source_directory/$val";
        if (isset($conf->t_keep) && is_array($conf->t_keep)) foreach($conf->t_keep as $key=>$val) $conf->t_keep[$key] = "$source_directory/$val";

        obfuscate_directory($source_directory,"$target_directory/yakpro-po/obfuscated");

        zip_files_from_dir("$target_directory/yakpro-po/obfuscated","$target_directory/eweb-obfuscated.zip");

        var_dump(scandir("$target_directory/yakpro-po/obfuscated"));
        exit('done');
}


function zip_files_from_dir($dir, $outputFile)
{
    // Get real path for our folder
    $rootPath = realpath($dir);

// Initialize archive object
    $zip = new ZipArchive();
    $zip->open($outputFile, ZipArchive::CREATE | ZipArchive::OVERWRITE);

// Initialize empty "delete list"
    $filesToDelete = array();

// Create recursive directory iterator
    /** @var SplFileInfo[] $files */
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($rootPath),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($files as $name => $file)
    {
        // Skip directories (they would be added automatically)
        if (!$file->isDir())
        {
            // Get real and relative path for current file
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($rootPath) + 1);

            // Add current file to archive
            $zip->addFile($filePath, $relativePath);

            // Add current file to "delete list"
            // delete it later cause ZipArchive create archive only after calling close function and ZipArchive lock files until archive created)
            if ($file->getFilename() != 'important.txt')
            {
                $filesToDelete[] = $filePath;
            }
        }
    }

// Zip archive will be created only after closing object
    $zip->close();
}
