<?php
require_once 'includes/functions.php';
require_once 'includes/auth.php';

logout_user();
flash('success', 'You have been logged out.');
redirect('index.php');
