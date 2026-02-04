<?php
require_once 'config.php';
require_once 'core/Helper.php';

echo "<h2>URL Test Sayfası</h2>";
echo "<hr>";

echo "<h3>Helper::url() Testleri:</h3>";
echo "<p><strong>rental:</strong> " . Helper::url('rental') . "</p>";
echo "<p><strong>rental/manage/3:</strong> " . Helper::url('rental/manage/3') . "</p>";
echo "<p><strong>rental/manage/3/ibans:</strong> " . Helper::url('rental/manage/3/ibans') . "</p>";

echo "<hr>";
echo "<h3>Link Testleri:</h3>";
echo '<p><a href="' . Helper::url('rental') . '">Kiralamalarım</a></p>';
echo '<p><a href="' . Helper::url('rental/manage/3') . '">Rental 3 - Dashboard</a></p>';
echo '<p><a href="' . Helper::url('rental/manage/3/ibans') . '">Rental 3 - İBANlar</a></p>';

echo "<hr>";
echo "<h3>Config Değerleri:</h3>";
echo "<p><strong>SITE_URL:</strong> " . SITE_URL . "</p>";
echo "<p><strong>BASE_PATH:</strong> " . BASE_PATH . "</p>";

echo "<hr>";
echo "<h3>Mevcut Request URI:</h3>";
echo "<p>" . $_SERVER['REQUEST_URI'] . "</p>";
