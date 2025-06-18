<?php
session_start();
session_unset();
session_destroy();
header("Location: ../public/index.php?logged_out=true");
exit();

?>
