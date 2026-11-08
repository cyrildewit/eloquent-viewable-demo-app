CREATE DATABASE IF NOT EXISTS `eloquent-viewable_demo`;
CREATE DATABASE IF NOT EXISTS `eloquent-viewable_demo_testing`;

GRANT ALL PRIVILEGES ON `eloquent-viewable_demo`.* TO 'homestead'@'%';
GRANT ALL PRIVILEGES ON `eloquent-viewable_demo_testing`.* TO 'homestead'@'%';

FLUSH PRIVILEGES;
