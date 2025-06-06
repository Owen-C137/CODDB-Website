<?php
/**
 * head_end.php
 *
 * Author: pixelcave
 *
 * (continue) The first block of code used in every page of the template
 *
 * The reason we separated head_start.php and head_end.php is for enabling
 * us to include between them extra plugin CSS files needed only in specific pages
 *
 */
?>

  <!-- Codebase framework -->
  <link rel="stylesheet" id="css-main" href="<?php echo $cb->assets_folder; ?>/css/codebase.min.css">

  <!-- You can include a specific file from css/themes/ folder to alter the default color theme of the template. eg: -->
  <!-- <link rel="stylesheet" id="css-theme" href="assets/css/themes/flat.min.css"> -->
  <?php if ($cb->theme) { ?>
    <link rel="stylesheet" id="css-theme" href="<?php echo $cb->assets_folder; ?>/css/themes/<?php echo $cb->theme; ?>.min.css">
  <?php } ?>
  <!-- END Stylesheets -->

  <!-- Load and set color theme + dark mode preference (blocking script to prevent flashing) -->
  <script src="<?php echo $cb->assets_folder; ?>/js/setTheme.js"></script>
</head>

<body>
