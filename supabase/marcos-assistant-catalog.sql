-- Marcos Home unified assistant catalog
-- Safe to re-run: data is upserted by code.

insert into assistant_products
  (code, category_ar, name_ar, aliases_ar, description_ar, price_text_ar, details_ar, active, sort_order)
values
  (
    'fire',
    'الفاير',
    'جهاز الفاير المعطر',
    array['الفاير','فاير','جهاز الفاير','المعطر','معطر الجو'],
    'جهاز فاير معطر يعمل بالماء والكهرباء ويمكن إضافة زيوت عطرية حسب الحاجة. يتوفر لدى ماركوز هوم مركز صيانة وقطع غيار أصلية واستبدال القطع التالفة بقطع أصلية عند الحاجة.',
    '40 سم: 85 د.ك، 70 سم: 135 د.ك، 1 متر: 180 د.ك، 1.20 متر: 220 د.ك، 1.50 متر: 270 د.ك',
    'يتوفر مركز صيانة وقطع غيار أصلية. في حالة حدوث عطل يمكن فحص الجهاز واستبدال القطع المطلوبة بقطع غيار أصلية.',
    true,
    10
  ),
  (
    'coffee-corner',
    'ركن القهوة',
    'ركن القهوة',
    array['ركن القهوة','كوفي كورنر','كوفي','coffee corner'],
    'ركن قهوة من ماركوز هوم يتكون من لوح فوم بورد V حتى ارتفاع السقف مع كبت بعرض 1 متر.',
    '35 د.ك بدون تركيب، 50 د.ك مع التركيب',
    'الكبت بعرض 1 متر، ارتفاع 25 سم وعمق 32 سم. اللوح حتى ارتفاع 2.90 متر تقريبًا. بدون إضاءة أسفل الكبت.',
    true,
    20
  ),
  (
    'tv-table-150',
    'الطاولات',
    'طاولة شاشة 1.5 متر',
    array['طاولة متر ونص','طاولة 1.5','ترابيزة متر ونص','طاولة شاشة 150'],
    'طاولة شاشة بطول 1.5 متر.',
    '40 د.ك بدون تركيب، 50 د.ك مع التركيب',
    'الخدمة متاحة بدون تركيب أو مع التركيب.',
    true,
    30
  ),
  (
    'tv-table-200',
    'الطاولات',
    'طاولة شاشة 2 متر',
    array['طاولة مترين','طاولة 2 متر','ترابيزة مترين','طاولة شاشة 200'],
    'طاولة شاشة بطول 2 متر.',
    '50 د.ك بدون تركيب، 60 د.ك مع التركيب',
    'الخدمة متاحة بدون تركيب أو مع التركيب.',
    true,
    31
  ),
  (
    'table-cabinet-3m',
    'خلفيات الشاشة',
    'طاولة 3 متر مع كبد مضيء',
    array['طاولة وكبد','طاولة 3 متر وكبد','كبد مضيء','طاولة ثلاثة متر'],
    'طاولة بطول 3 متر مع كبد جانبي ارتفاع 2 متر وعرض 40 سم، وبداخل الكبد أرفف بإضاءة.',
    '100 د.ك بدون تركيب، 115 د.ك مع التركيب',
    'زيادة طول الطاولة عن 3 متر: نصف متر إضافي +15 د.ك، متر كامل إضافي +25 د.ك.',
    true,
    40
  ),
  (
    'wpc-column',
    'أعمدة WPC',
    'عمود WPC',
    array['عمود','اعمدة','أعمدة','wpc','عمود ديكور'],
    'عمود WPC ارتفاع 2.90 متر ومقطع 5×10 سم. يمكن تركيبه مستقيمًا بواجهة ظاهرة 5 سم وعمق 10 سم، أو مائلًا بزاوية 45 درجة حسب اختيار العميل.',
    '5 د.ك للعمود بدون تركيب، 7 د.ك للعمود مع التركيب',
    'كل متر عرض يحتاج تقريبًا من 8 إلى 10 أعمدة حسب المسافات وطريقة التركيب. يوجد تركيب مستقيم أو مائل 45 درجة.',
    true,
    50
  ),
  (
    'screen-hanging',
    'خدمات إضافية',
    'تعليق الشاشة',
    array['تعليق الشاشة','تركيب الشاشة','تعليق تلفزيون','تعليق التلفزيون'],
    'خدمة تعليق الشاشة اختيارية عند تنفيذ التصميم.',
    '5 د.ك إضافية',
    'أعمال الكهرباء أو التمديدات أو التكسير داخل الحائط ليست ضمن مسؤولية ماركوز هوم وتكون عن طريق كهربائي على مسؤولية العميل.',
    true,
    60
  )
on conflict (code) do update set
  category_ar = excluded.category_ar,
  name_ar = excluded.name_ar,
  aliases_ar = excluded.aliases_ar,
  description_ar = excluded.description_ar,
  price_text_ar = excluded.price_text_ar,
  details_ar = excluded.details_ar,
  active = excluded.active,
  sort_order = excluded.sort_order;

insert into assistant_offers
  (code, name_ar, min_width, max_width, height_m, price_without_installation, price_with_installation, components_ar, active, sort_order)
values
  (
    'design-198',
    'تصميم 198',
    3.5,
    4.5,
    2.90,
    158,
    198,
    'طاولة بطول 3 متر + كبد جانبي ارتفاع 2 متر وعرض 40 سم، داخله أرفف بإضاءة + 4 ألواح، مقاس اللوح الواحد 1.22 متر عرض × 2.90 متر ارتفاع. بدون تركيب يستلم العميل كامل المكونات ويوفر 40 د.ك.',
    true,
    10
  )
on conflict (code) do update set
  name_ar = excluded.name_ar,
  min_width = excluded.min_width,
  max_width = excluded.max_width,
  height_m = excluded.height_m,
  price_without_installation = excluded.price_without_installation,
  price_with_installation = excluded.price_with_installation,
  components_ar = excluded.components_ar,
  active = excluded.active,
  sort_order = excluded.sort_order;

-- Business rules used by the assistant:
-- 1) Design 198 base wall width: 3.5m to 4.5m, standard height 2.90m.
-- 2) If the wall exceeds 4.5m and an extra panel is required, add 25 KWD per additional panel.
-- 3) Base table length in Design 198 is 3m. Increase to 3.5m: +15 KWD. Increase to 4m: +25 KWD.
-- 4) Marcos Home does not perform electrical extensions, wall chasing/breaking, or relocation of electrical points. These are the client's electrician responsibility.
-- 5) TV/screen hanging is an optional +5 KWD service.
