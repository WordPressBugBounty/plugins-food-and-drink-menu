<?php
if ( ! defined( 'ABSPATH' ) ) exit;
global $fdm_controller;
$template = $fdm_controller->implementation->template_path( 'admin-order-table-item' );
if ( $template ) include $template;
