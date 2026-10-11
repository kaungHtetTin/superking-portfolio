<?php

// Supply your own callbacks before marking routes as protected.
return ['check' => fn () => isset($_SESSION['admin_id']), 'can' => fn () => isset($_SESSION['admin_id']), 'login' => 'admin/login'];
