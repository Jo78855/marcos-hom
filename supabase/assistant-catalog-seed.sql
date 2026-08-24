-- Existing Marco's Home catalog data for the assistant.
-- Safe to run repeatedly after assistant_products / assistant_offers exist.
insert into public.assistant_products (code,category_ar,name_ar,aliases_ar,description_ar,price_text_ar,details_ar,active,sort_order) values
('coffee_corner','ديكور','ركن القهوة',array['كوفي كورنر','coffee corner','ركن قهوة'],'ركن قهوة من ماركوز هوم، متاح بسبعة ألوان جاهزة.','35 د.ك بدون تركيب، أو 50 د.ك شامل التركيب.','ارتفاع حتى 2.90 م، لوح ديكور بعرض تقريبي 1.20 م، وكبت سفلي بعرض 1 متر. متاح بعدة ألوان.',true,1),
('fire','ديكور','جهاز الفير المعطر',array['الفير','فاير','fire','المعطر'],'جهاز فير معطر بلهب مائي ثلاثي الأبعاد، يعمل بالماء والكهرباء ويمكن إضافة زيوت عطرية، مع صيانة وقطع غيار.','40 سم: 85 د.ك، 70 سم: 135 د.ك، 1 متر: 180 د.ك، 1.20 متر: 220 د.ك، 1.50 متر: 270 د.ك.','السعر حسب طول الجهاز.',true,2),
('tv_console','أثاث','طاولة تلفزيون',array['طاولة','كونسول','طاولة شاشة','tv console'],'طاولة تلفزيون معلقة من ماركوز هوم.','1.5 متر: 40 د.ك بدون تركيب و50 د.ك مع التركيب. 2 متر: 50 د.ك بدون تركيب و60 د.ك مع التركيب.','ارتفاع 25 سم وعمق 32 سم، ومتاحة بعدة ألوان.',true,3),
('wpc_columns','ديكور','فواصل WPC',array['فواصل','اعمدة','أعمدة','wpc'],'فواصل وأعمدة WPC للديكور الداخلي.','5 د.ك بدون تركيب أو 7 د.ك مع التركيب للعمود.','مقاس 5×10 سم وارتفاع حتى 2.90 م.',true,4)
on conflict (code) do update set category_ar=excluded.category_ar,name_ar=excluded.name_ar,aliases_ar=excluded.aliases_ar,description_ar=excluded.description_ar,price_text_ar=excluded.price_text_ar,details_ar=excluded.details_ar,active=true,sort_order=excluded.sort_order;

insert into public.assistant_offers (code,name_ar,min_width,max_width,height_m,price_without_installation,price_with_installation,components_ar,active,sort_order) values
('design198_3_35','تصميم 198',3.0,3.5,2.9,130,170,'طاولة 2.5 متر + كبت + 3 ألواح فوم بورد',true,1),
('design198_35_45','تصميم 198',3.5,4.5,2.9,150,198,'طاولة 3 متر + كبت + 4 ألواح فوم بورد',true,2),
('design198_46_55','تصميم 198',4.6,5.5,2.9,160,210,'خلفية شاشة متكاملة حسب الفئة والمقاس',true,3)
on conflict (code) do update set name_ar=excluded.name_ar,min_width=excluded.min_width,max_width=excluded.max_width,height_m=excluded.height_m,price_without_installation=excluded.price_without_installation,price_with_installation=excluded.price_with_installation,components_ar=excluded.components_ar,active=true,sort_order=excluded.sort_order;
