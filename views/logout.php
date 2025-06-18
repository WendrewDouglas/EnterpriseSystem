<?php
session_start();
session_unset();
session_destroy();
header("Location: index.php?page=login&message=logout_success");
exit();
?>
