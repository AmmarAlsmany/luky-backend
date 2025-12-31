<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StaticPagesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pages = [
            [
                'slug' => 'provider-acknowledgment',
                'title_en' => 'Provider Acknowledgment',
                'title_ar' => 'إقرار مقدم الخدمة',
                'content_en' => '<h2>Provider Acknowledgment</h2>
<p>By registering as a service provider on the Luky platform, I acknowledge and confirm the following:</p>

<h3>1. Accuracy of Information</h3>
<p>I confirm that all information provided during registration, including but not limited to business name, contact details, business address, licenses, and certifications, is accurate, complete, and up-to-date.</p>

<h3>2. Business Legitimacy</h3>
<p>I confirm that I am legally authorized to operate my business and provide the services listed on this platform in accordance with all applicable laws and regulations of the Kingdom of Saudi Arabia.</p>

<h3>3. License and Permits</h3>
<p>I confirm that I hold all necessary licenses, permits, and certifications required to provide the services I am offering, and that these documents are valid and current.</p>

<h3>4. Document Authenticity</h3>
<p>I acknowledge that all documents uploaded (including business licenses, permits, identification, and certificates) are authentic and have not been altered, forged, or falsified in any way.</p>

<h3>5. Professional Qualifications</h3>
<p>I confirm that I and my staff possess the necessary skills, qualifications, and experience to deliver the services advertised on the platform to a professional standard.</p>

<h3>6. Understanding of Consequences</h3>
<p>I understand that providing false information or fraudulent documents may result in:</p>
<ul>
<li>Immediate suspension or termination of my account</li>
<li>Legal action as per Saudi Arabian law</li>
<li>Permanent ban from the platform</li>
<li>Financial penalties as stated in the terms and conditions</li>
</ul>

<h3>7. Data Updates</h3>
<p>I commit to promptly updating my profile and business information whenever there are changes to my business details, licenses, or contact information.</p>

<h3>8. Verification Process</h3>
<p>I acknowledge that my application and documents will be reviewed by the Luky platform administration, and I agree to cooperate fully with any verification processes.</p>

<p><strong>By checking this acknowledgment, I confirm that I have read, understood, and agree to all the points mentioned above.</strong></p>',
                'content_ar' => '<h2>إقرار مقدم الخدمة</h2>
<p>بالتسجيل كمقدم خدمة على منصة لكي، أقر وأؤكد ما يلي:</p>

<h3>١. دقة المعلومات</h3>
<p>أؤكد أن جميع المعلومات المقدمة أثناء التسجيل، بما في ذلك على سبيل المثال لا الحصر اسم العمل والبيانات التواصل وعنوان العمل والتراخيص والشهادات، دقيقة وكاملة ومحدثة.</p>

<h3>٢. شرعية العمل</h3>
<p>أؤكد أنني مخول قانونياً بتشغيل عملي وتقديم الخدمات المدرجة على هذه المنصة وفقاً لجميع القوانين واللوائح المعمول بها في المملكة العربية السعودية.</p>

<h3>٣. التراخيص والتصاريح</h3>
<p>أؤكد أنني أحمل جميع التراخيص والتصاريح والشهادات اللازمة لتقديم الخدمات التي أعرضها، وأن هذه الوثائق سارية المفعول وحديثة.</p>

<h3>٤. صحة الوثائق</h3>
<p>أقر بأن جميع الوثائق المحملة (بما في ذلك تراخيص العمل والتصاريح والهوية والشهادات) أصلية ولم يتم تعديلها أو تزويرها أو تزييفها بأي شكل من الأشكال.</p>

<h3>٥. المؤهلات المهنية</h3>
<p>أؤكد أنني وموظفيي نمتلك المهارات والمؤهلات والخبرة اللازمة لتقديم الخدمات المعلن عنها على المنصة بمستوى احترافي.</p>

<h3>٦. فهم العواقب</h3>
<p>أدرك أن تقديم معلومات كاذبة أو مستندات مزورة قد يؤدي إلى:</p>
<ul>
<li>إيقاف فوري أو إنهاء حسابي</li>
<li>إجراء قانوني وفقاً للقانون السعودي</li>
<li>حظر دائم من المنصة</li>
<li>عقوبات مالية كما هو مذكور في الشروط والأحكام</li>
</ul>

<h3>٧. تحديث البيانات</h3>
<p>ألتزم بتحديث ملفي الشخصي ومعلومات عملي فوراً عند حدوث أي تغييرات على تفاصيل عملي أو التراخيص أو معلومات الاتصال.</p>

<h3>٨. عملية التحقق</h3>
<p>أقر بأن طلبي ووثائقي ستتم مراجعتها من قبل إدارة منصة لكي، وأوافق على التعاون الكامل مع أي عمليات تحقق.</p>

<p><strong>بالتأشير على هذا الإقرار، أؤكد أنني قرأت وفهمت ووافقت على جميع النقاط المذكورة أعلاه.</strong></p>',
                'meta_description_en' => 'Provider acknowledgment and confirmation of information accuracy',
                'meta_description_ar' => 'إقرار مقدم الخدمة وتأكيد دقة المعلومات',
                'is_published' => true,
            ],
            [
                'slug' => 'provider-undertaking',
                'title_en' => 'Provider Undertaking Agreement',
                'title_ar' => 'اتفاقية تعهد مقدم الخدمة',
                'content_en' => '<h2>Provider Undertaking Agreement</h2>
<p>By registering as a service provider on the Luky platform, I undertake and commit to the following:</p>

<h3>1. Service Quality</h3>
<p>I undertake to provide high-quality professional services to all customers in accordance with industry standards and best practices.</p>

<h3>2. Platform Terms and Conditions</h3>
<p>I agree to abide by all terms and conditions, policies, and guidelines set forth by the Luky platform, including but not limited to:</p>
<ul>
<li>Service delivery standards</li>
<li>Pricing policies</li>
<li>Cancellation and refund policies</li>
<li>Communication guidelines</li>
<li>Professional conduct requirements</li>
</ul>

<h3>3. Customer Service</h3>
<p>I commit to:</p>
<ul>
<li>Treat all customers with respect and professionalism</li>
<li>Respond promptly to booking requests and customer inquiries</li>
<li>Arrive on time for scheduled appointments</li>
<li>Complete services as agreed and described</li>
<li>Address customer concerns and complaints professionally</li>
</ul>

<h3>4. Safety and Hygiene</h3>
<p>I undertake to maintain the highest standards of safety and hygiene in the delivery of my services, including:</p>
<ul>
<li>Using clean and sanitized equipment and tools</li>
<li>Following health and safety regulations</li>
<li>Maintaining a clean and safe working environment</li>
<li>Using quality products and materials</li>
</ul>

<h3>5. Honest Practices</h3>
<p>I commit to:</p>
<ul>
<li>Accurate representation of services, prices, and availability</li>
<li>No hidden fees or unexpected charges</li>
<li>Transparent communication about service limitations</li>
<li>Honest reviews and feedback responses</li>
</ul>

<h3>6. Legal Compliance</h3>
<p>I undertake to comply with all applicable laws and regulations of the Kingdom of Saudi Arabia in the provision of my services.</p>

<h3>7. Platform Reputation</h3>
<p>I understand that my conduct reflects on the Luky platform, and I commit to upholding the platform\'s reputation through professional and ethical business practices.</p>

<h3>8. Commission and Payments</h3>
<p>I agree to the commission structure as outlined in the provider agreement and undertake to process all bookings and payments through the platform\'s official channels.</p>

<h3>9. Continuous Improvement</h3>
<p>I commit to continuously improving my services based on customer feedback and platform recommendations.</p>

<h3>10. Account Suspension/Termination</h3>
<p>I understand that failure to uphold these undertakings may result in warnings, account suspension, or permanent termination from the platform.</p>

<p><strong>By checking this undertaking agreement, I confirm that I have read, understood, and agree to fulfill all commitments mentioned above.</strong></p>',
                'content_ar' => '<h2>اتفاقية تعهد مقدم الخدمة</h2>
<p>بالتسجيل كمقدم خدمة على منصة لكي، أتعهد وألتزم بما يلي:</p>

<h3>١. جودة الخدمة</h3>
<p>أتعهد بتقديم خدمات احترافية عالية الجودة لجميع العملاء وفقاً لمعايير الصناعة وأفضل الممارسات.</p>

<h3>٢. شروط وأحكام المنصة</h3>
<p>أوافق على الالتزام بجميع الشروط والأحكام والسياسات والإرشادات التي وضعتها منصة لكي، بما في ذلك على سبيل المثال لا الحصر:</p>
<ul>
<li>معايير تقديم الخدمة</li>
<li>سياسات التسعير</li>
<li>سياسات الإلغاء والاسترداد</li>
<li>إرشادات التواصل</li>
<li>متطلبات السلوك المهني</li>
</ul>

<h3>٣. خدمة العملاء</h3>
<p>ألتزم بـ:</p>
<ul>
<li>معاملة جميع العملاء بالاحترام والمهنية</li>
<li>الرد بسرعة على طلبات الحجز واستفسارات العملاء</li>
<li>الوصول في الوقت المحدد للمواعيد المقررة</li>
<li>إكمال الخدمات كما هو متفق عليه وموصوف</li>
<li>معالجة مخاوف وشكاوى العملاء بشكل احترافي</li>
</ul>

<h3>٤. السلامة والنظافة</h3>
<p>أتعهد بالحفاظ على أعلى معايير السلامة والنظافة في تقديم خدماتي، بما في ذلك:</p>
<ul>
<li>استخدام معدات وأدوات نظيفة ومعقمة</li>
<li>اتباع لوائح الصحة والسلامة</li>
<li>الحفاظ على بيئة عمل نظيفة وآمنة</li>
<li>استخدام منتجات ومواد عالية الجودة</li>
</ul>

<h3>٥. الممارسات الصادقة</h3>
<p>ألتزم بـ:</p>
<ul>
<li>التمثيل الدقيق للخدمات والأسعار والتوافر</li>
<li>عدم وجود رسوم مخفية أو تكاليف غير متوقعة</li>
<li>التواصل الشفاف حول قيود الخدمة</li>
<li>المراجعات الصادقة والردود على التعليقات</li>
</ul>

<h3>٦. الامتثال القانوني</h3>
<p>أتعهد بالامتثال لجميع القوانين واللوائح المعمول بها في المملكة العربية السعودية في تقديم خدماتي.</p>

<h3>٧. سمعة المنصة</h3>
<p>أدرك أن سلوكي ينعكس على منصة لكي، وألتزم بالحفاظ على سمعة المنصة من خلال ممارسات تجارية مهنية وأخلاقية.</p>

<h3>٨. العمولة والمدفوعات</h3>
<p>أوافق على هيكل العمولة كما هو موضح في اتفاقية مقدم الخدمة وأتعهد بمعالجة جميع الحجوزات والمدفوعات من خلال القنوات الرسمية للمنصة.</p>

<h3>٩. التحسين المستمر</h3>
<p>ألتزم بالتحسين المستمر لخدماتي بناءً على ملاحظات العملاء وتوصيات المنصة.</p>

<h3>١٠. إيقاف/إنهاء الحساب</h3>
<p>أدرك أن عدم الوفاء بهذه التعهدات قد يؤدي إلى تحذيرات أو إيقاف الحساب أو الإنهاء الدائم من المنصة.</p>

<p><strong>بالتأشير على اتفاقية التعهد هذه، أؤكد أنني قرأت وفهمت ووافقت على الوفاء بجميع الالتزامات المذكورة أعلاه.</strong></p>',
                'meta_description_en' => 'Provider undertaking and service commitments',
                'meta_description_ar' => 'تعهد مقدم الخدمة والتزامات الخدمة',
                'is_published' => true,
            ],
            [
                'slug' => 'terms-and-conditions',
                'title_en' => 'Terms and Conditions',
                'title_ar' => 'الشروط والأحكام',
                'content_en' => '<h2>Terms and Conditions</h2>
<p>Welcome to Luky Platform. These terms and conditions outline the rules and regulations for the use of our service.</p>

<h3>1. Acceptance of Terms</h3>
<p>By accessing and using this platform, you accept and agree to be bound by the terms and provisions of this agreement.</p>

<h3>2. Services</h3>
<p>Luky provides a platform connecting service providers with customers seeking beauty and wellness services.</p>

<h3>3. User Accounts</h3>
<p>Users must provide accurate and complete information when creating an account and keep their account information updated.</p>

<h3>4. Privacy</h3>
<p>Your privacy is important to us. Please review our Privacy Policy to understand how we collect and use your information.</p>

<p>For the complete terms and conditions, please contact our support team.</p>',
                'content_ar' => '<h2>الشروط والأحكام</h2>
<p>مرحباً بك في منصة لكي. توضح هذه الشروط والأحكام القواعد واللوائح الخاصة باستخدام خدمتنا.</p>

<h3>١. قبول الشروط</h3>
<p>بالدخول واستخدام هذه المنصة، فإنك تقبل وتوافق على الالتزام بشروط وأحكام هذه الاتفاقية.</p>

<h3>٢. الخدمات</h3>
<p>توفر لكي منصة تربط مقدمي الخدمات بالعملاء الباحثين عن خدمات التجميل والعناية.</p>

<h3>٣. حسابات المستخدمين</h3>
<p>يجب على المستخدمين تقديم معلومات دقيقة وكاملة عند إنشاء حساب والحفاظ على تحديث معلومات حساباتهم.</p>

<h3>٤. الخصوصية</h3>
<p>خصوصيتك مهمة بالنسبة لنا. يرجى مراجعة سياسة الخصوصية لفهم كيفية جمع واستخدام معلوماتك.</p>

<p>للحصول على الشروط والأحكام الكاملة، يرجى الاتصال بفريق الدعم لدينا.</p>',
                'meta_description_en' => 'Luky platform terms and conditions',
                'meta_description_ar' => 'شروط وأحكام منصة لكي',
                'is_published' => true,
            ],
            [
                'slug' => 'privacy-policy',
                'title_en' => 'Privacy Policy',
                'title_ar' => 'سياسة الخصوصية',
                'content_en' => '<h2>Privacy Policy</h2>
<p>This Privacy Policy describes how Luky collects, uses, and protects your personal information.</p>

<h3>1. Information We Collect</h3>
<p>We collect information you provide directly to us, including name, email, phone number, and business details.</p>

<h3>2. How We Use Your Information</h3>
<p>We use your information to provide and improve our services, process bookings, and communicate with you.</p>

<h3>3. Data Security</h3>
<p>We implement appropriate security measures to protect your personal information.</p>

<h3>4. Your Rights</h3>
<p>You have the right to access, update, or delete your personal information.</p>

<p>For the complete privacy policy, please contact our support team.</p>',
                'content_ar' => '<h2>سياسة الخصوصية</h2>
<p>توضح سياسة الخصوصية هذه كيفية قيام لكي بجمع واستخدام وحماية معلوماتك الشخصية.</p>

<h3>١. المعلومات التي نجمعها</h3>
<p>نجمع المعلومات التي تقدمها لنا مباشرة، بما في ذلك الاسم والبريد الإلكتروني ورقم الهاتف وتفاصيل العمل.</p>

<h3>٢. كيفية استخدام معلوماتك</h3>
<p>نستخدم معلوماتك لتقديم وتحسين خدماتنا ومعالجة الحجوزات والتواصل معك.</p>

<h3>٣. أمن البيانات</h3>
<p>نطبق تدابير أمنية مناسبة لحماية معلوماتك الشخصية.</p>

<h3>٤. حقوقك</h3>
<p>لديك الحق في الوصول إلى معلوماتك الشخصية أو تحديثها أو حذفها.</p>

<p>للحصول على سياسة الخصوصية الكاملة، يرجى الاتصال بفريق الدعم لدينا.</p>',
                'meta_description_en' => 'Luky platform privacy policy',
                'meta_description_ar' => 'سياسة خصوصية منصة لكي',
                'is_published' => true,
            ],
        ];

        foreach ($pages as $page) {
            DB::table('static_pages')->updateOrInsert(
                ['slug' => $page['slug']],
                array_merge($page, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('Static pages seeded successfully!');
    }
}
