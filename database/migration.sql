CREATE TABLE `access_attemp` (
  `id` int(30) NOT NULL,
  `user_id` int(30) NOT NULL,
  `date` datetime NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT 1 COMMENT '0= fail,1=success'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
  
ALTER TABLE `access_attemp`
  ADD PRIMARY KEY (`id`);
  
ALTER TABLE `access_attemp`
  MODIFY `id` int(30) NOT NULL AUTO_INCREMENT;