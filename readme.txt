=== PayMaster for WooCommerce ===
Contributors: andry.chuyko@gmail.com
Tags: paymaster, payment getaway, woo commerce, woocommerce, ecommerce
Requires at least: 3.0
Tested up to: 3.9

Allows you to use PayMaster payment gateway with the WooCommerce plugin.

== Description ==

После активации плагина через панель управления в WooCommerce прописывем
Идентификатор, Секретная фраза узнать их можно в [личном кабинете paymaster](https://paymaster.ru/Partners/)


В PayMaster прописываем:
<ul style="list-style:none;">
<li>Result URL: http://your_domain/?wc-api=wc_paymaster&paymaster=result</li>
<li>Success URL: http://your_domain/?wc-api=wc_paymaster&paymaster=success</li>
<li>Fail URL: http://your_domain/?wc-api=wc_paymaster&paymaster=fail</li>
<li>Метод отсылки данных: POST</li>
</ul>

== Installation ==
1. Убедитесь что у вас установлена посленяя версия плагина [WooCommerce](/www.woothemes.com/woocommerce)
2. Распакуйте архив и загрузите "paymaster-for-woocommerce" в папку ваш-домен/wp-content/plugins
3. Активируйте плагин


== Changelog ==
= 1.0.0 =
* Релиз плагина
