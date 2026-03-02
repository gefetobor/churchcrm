-- First timer postcode support
ALTER TABLE `first_timer_ft`
  ADD COLUMN `ft_Postcode` varchar(50) DEFAULT NULL AFTER `ft_Address`;
