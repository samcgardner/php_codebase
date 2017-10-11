<?php

namespace tools\build;

use \Exception;

/** PHP library buider. */
class Builder {

  /** Builds a PHP library. */
  public static function build(
      $type, $out_dir, $srcs, $deps, $target, $bootstrap) {
    $php_files = array();
    foreach ($srcs as $src) {
      if (strtolower(substr($src, -4)) == ".php") {
        self::checkSyntax($src);
        $php_files[] = $src;
      }
      self::placeFile($src, $out_dir);
    }

    if ($bootstrap) {
      echo "Bootstraping {$target}\n";
      self::bootstrap($type, $out_dir, $php_files, $deps, $target);
      echo "Successfully bootstrapped target {$target}.\n";
    }
  }

  private static function bootstrap($type, $out_dir, $srcs, $deps, $target) {
    $path_parts = explode("/", $srcs[0]);
    $class = str_replace(".php", "", array_pop($path_parts));
    $target_dir = implode("/", $path_parts);
    $path_len = count($path_parts);
    $namespace = implode("\\", $path_parts);
    $target_app_root =
        strtoupper("APP_ROOT_" . implode("_", $path_parts) . "_{$target}");
    $relpath = implode("/", array_fill(0, $path_len, ".."));

    $out_file = "${out_dir}/{$target_dir}/{$target}.bootstrap.php";
    $all_deps = array_merge($deps, $srcs);
    $vars = array('{srcs}' => var_export($srcs, true),
                  '{deps}' => var_export(array_flip($all_deps), true),
                  '{relpath}' => $relpath,
                  '{namespace}' => $namespace,
                  '{class}' => $class,
                  '{target}' => "{$target_dir}/{$target}",
                  '{target_app_root}' => $target_app_root);
    self::concat($out_file,
                 array("autoload_template.php", "lib_template.php"),
                 $vars);
    self::runCommand($out_file, "Failed bootstrapping target {$target}");

    if ($type == "executable")  {
      $exe_file = "${out_dir}/{$target_dir}/{$target}";
      self::concat($exe_file,
                   array("autoload_template.php", "exe_template.php"),
                   $vars);
    } else if ($type == "test") {
      $test_file = "${out_dir}/{$target_dir}/{$target}";
      self::concat($test_file,
                   array("autoload_template.php", "test_template.php"),
                   $vars);
    }
  }

  private static function concat($output_file, $templates, $replacements) {
    $out = "";
    foreach ($templates as $i => $tpl) {
      $contents =
          strtr(file_get_contents(__DIR__ . "/$tpl"), $replacements);
      if ($i > 0) {
        $contents = str_replace('<?php', '', $contents);
      }
      $out .= trim($contents) . "\n";
    }
    file_put_contents($output_file, $out);
    chmod($output_file, 0755);
  }

  /** Copies a file to the output directory. */
  private function placeFile($filename, $out_dir) {
    $dest_file = self::join_path($out_dir, $filename);
    $dest_dir = dirname($dest_file);
    if (!file_exists($dest_dir)) {
      mkdir($dest_dir, 0755, true);
    }
    echo "Placing file: $filename\n";
    copy($filename, $dest_file);
  }

  private static function checkSyntax($filename) {
    echo "Checking syntax: $filename\n";
    self::runCommand(
        "php -l $filename > /dev/null", "Invalid syntax in $filename");
  }

  private static function checkFileExists($filename) {
    if (!file_exists($filename)) {
      throw new Exception("Required file does not exist: $filename");
    }
  }

  private static function runCommand($cmd, $error="Error running cmd") {
    system("$cmd", $retval);
    if ($retval != 0) {
      throw new Exception($error);
    }
  }

  private static function join_path() {
    return implode("/", func_get_args());
  }
}