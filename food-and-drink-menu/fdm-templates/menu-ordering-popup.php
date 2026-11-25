<?php
if ( ! defined( 'ABSPATH' ) ) exit;
global $fdm_controller;
$template = $fdm_controller->implementation->template_path( 'menu-ordering-popup' );
if ( $template ) include $template;
