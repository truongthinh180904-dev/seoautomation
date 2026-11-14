<?php

namespace App\Services\Brand;

class IndustryProfileRegistry
{
    public const INDUSTRY_HEALTHCARE = 'healthcare';
    public const INDUSTRY_LEGAL = 'legal';
    public const INDUSTRY_REAL_ESTATE = 'real_estate';
    public const INDUSTRY_EDUCATION = 'education';
    public const INDUSTRY_TECHNOLOGY = 'technology';
    public const INDUSTRY_ECOMMERCE = 'ecommerce';
    public const INDUSTRY_TRAVEL = 'travel';
    public const INDUSTRY_FINANCE = 'finance';
    public const INDUSTRY_MANUFACTURING = 'manufacturing';
    public const INDUSTRY_BEAUTY = 'beauty';
    public const INDUSTRY_FOOD_BEVERAGE = 'food_beverage';
    public const INDUSTRY_OTHER = 'other';

    /**
     * Custom runtime-registered industry profiles (allows dynamic extension without modifying core).
     *
     * @var array<string, array<string, mixed>>
     */
    protected static array $customProfiles = [];

    /**
     * Allow registering or overriding an Industry Profile dynamically.
     *
     * @param  array<string, mixed>  $profileConfig
     */
    public static function register(string $key, array $profileConfig): void
    {
        $normalizedKey = strtolower(trim($key));
        self::$customProfiles[$normalizedKey] = self::normalizeProfile(array_merge(
            self::defaultProfileTemplate($normalizedKey),
            $profileConfig,
            ['key' => $normalizedKey]
        ));
    }

    /**
     * Allow instance-based registration (alias for static register).
     *
     * @param  array<string, mixed>  $profileConfig
     */
    public function registerCustomProfile(string $key, array $profileConfig): void
    {
        self::register($key, $profileConfig);
    }

    /**
     * Get all available Industry Profiles.
     *
     * @return array<string, array<string, mixed>>
     */
    public function all(): array
    {
        $merged = array_merge($this->builtInProfiles(), self::$customProfiles);
        foreach ($merged as $k => $profile) {
            $merged[$k] = self::normalizeProfile($profile);
        }

        return $merged;
    }

    /**
     * Alias for all() used by controllers and services.
     *
     * @return array<string, array<string, mixed>>
     */
    public function allProfiles(): array
    {
        return $this->all();
    }

    /**
     * Get a specific Industry Profile by key, falling back to 'other' if unknown.
     *
     * @return array<string, mixed>
     */
    public function get(?string $key): array
    {
        $normalizedKey = strtolower(trim((string) $key));
        $all = $this->all();

        return $all[$normalizedKey] ?? $all['other'];
    }

    /**
     * Alias for get() used across services and controllers.
     *
     * @return array<string, mixed>
     */
    public function getProfile(?string $key): array
    {
        return $this->get($key);
    }

    /**
     * Normalize profile keys so both legacy and new callers have consistent fields.
     *
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    protected static function normalizeProfile(array $profile): array
    {
        $isYmyl = (bool) ($profile['is_ymyl'] ?? (($profile['ymyl_level'] ?? 'low') === 'high'));
        $ymylLevel = $profile['ymyl_level'] ?? ($isYmyl ? 'high' : 'low');
        $verification = $profile['verification_requirement'] ?? $profile['verification_level'] ?? ($isYmyl ? 'strict' : 'standard');
        $disclaimer = $profile['required_disclaimer'] ?? $profile['disclaimer_template'] ?? null;
        $ctas = $profile['preferred_cta_types'] ?? $profile['suitable_ctas'] ?? [];

        $profile['is_ymyl'] = $isYmyl;
        $profile['ymyl_level'] = $ymylLevel;
        $profile['verification_level'] = $verification;
        $profile['verification_requirement'] = $verification;
        $profile['disclaimer_template'] = $disclaimer;
        $profile['required_disclaimer'] = $disclaimer;
        $profile['suitable_ctas'] = $ctas;
        $profile['preferred_cta_types'] = $ctas;
        $profile['terminology'] = $profile['terminology'] ?? ($profile['keywords_signals'] ?? []);
        $profile['content_structures'] = $profile['content_structures'] ?? ($profile['recommended_structures'] ?? []);

        return $profile;
    }

    /**
     * Automatically classify a business or topic into the most appropriate Industry Profile key.
     * Accepts either an associative array of signals OR positional string arguments.
     *
     * @param  array<string, mixed>|string|null  $primaryIndustry
     * @param  array<int, mixed>  $products
     */
    public function detectIndustry(
        array|string|null $primaryIndustry = null,
        ?string $keyword = null,
        ?string $companyDescription = null,
        array $products = []
    ): string {
        $profiles = $this->all();

        $extraParts = [];
        if (is_array($primaryIndustry)) {
            $payload = $primaryIndustry;
            $explicitKey = strtolower(trim((string) ($payload['industry_key'] ?? '')));
            if ($explicitKey !== '' && $explicitKey !== self::INDUSTRY_OTHER && isset($profiles[$explicitKey])) {
                return $explicitKey;
            }

            $primaryIndustry = (string) ($payload['primary_industry'] ?? '');
            $keyword = $keyword ?? (string) ($payload['secondary_industry'] ?? $payload['keyword'] ?? '');
            $companyDescription = $companyDescription ?? implode(' ', array_filter([
                (string) ($payload['brand_name'] ?? ''),
                (string) ($payload['company_name'] ?? ''),
                (string) ($payload['tagline'] ?? ''),
                (string) ($payload['company_intro'] ?? ''),
            ]));
            $products = ! empty($products) ? $products : (is_array($payload['product_catalog'] ?? null) ? $payload['product_catalog'] : []);
            if (! empty($payload['unique_selling_points']) && is_array($payload['unique_selling_points'])) {
                foreach ($payload['unique_selling_points'] as $usp) {
                    if (is_string($usp)) {
                        $extraParts[] = $usp;
                    }
                }
            }
        }

        // 1. Direct match by key or Vietnamese/English name
        if ($primaryIndustry !== null && trim($primaryIndustry) !== '') {
            $cleanPrimary = mb_strtolower(trim($primaryIndustry), 'UTF-8');
            if (isset($profiles[$cleanPrimary])) {
                return $cleanPrimary;
            }

            foreach ($profiles as $key => $profile) {
                $nameEn = mb_strtolower((string) ($profile['name'] ?? ''), 'UTF-8');
                $labelVi = mb_strtolower((string) ($profile['label_vi'] ?? ''), 'UTF-8');
                if ($cleanPrimary === $nameEn || $cleanPrimary === $labelVi) {
                    return (string) $key;
                }
            }
        }

        // 2. Score-based detection across signals
        $productNames = [];
        foreach ($products as $prod) {
            if (is_array($prod) && ! empty($prod['name'])) {
                $productNames[] = $prod['name'].' '.($prod['description'] ?? '');
            } elseif (is_string($prod)) {
                $productNames[] = $prod;
            }
        }

        $combinedCorpus = mb_strtolower(
            implode(' ', array_filter([
                $primaryIndustry,
                $keyword,
                $companyDescription,
                implode(' ', $productNames),
                implode(' ', $extraParts),
            ])),
            'UTF-8'
        );

        if (trim($combinedCorpus) === '') {
            return self::INDUSTRY_OTHER;
        }

        $bestKey = self::INDUSTRY_OTHER;
        $bestScore = 0;

        foreach ($profiles as $key => $profile) {
            if ($key === self::INDUSTRY_OTHER) {
                continue;
            }

            $score = 0;
            $signals = $profile['keywords_signals'] ?? [];
            foreach ($signals as $signal) {
                $signalLower = mb_strtolower((string) $signal, 'UTF-8');
                if ($signalLower !== '' && str_contains($combinedCorpus, $signalLower)) {
                    // Weight primaryIndustry matches higher than general corpus
                    if ($primaryIndustry && str_contains(mb_strtolower($primaryIndustry, 'UTF-8'), $signalLower)) {
                        $score += 5;
                    } elseif ($keyword && str_contains(mb_strtolower($keyword, 'UTF-8'), $signalLower)) {
                        $score += 3;
                    } else {
                        $score += 2;
                    }
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestKey = (string) $key;
            }
        }

        return isset($profiles[$bestKey]) ? $bestKey : self::INDUSTRY_OTHER;
    }

    /**
     * Build a structured prompt instruction block for the given Industry Profile.
     */
    public function buildIndustryPromptInstructions(string|array $industryOrKey): string
    {
        $profile = is_array($industryOrKey) ? $industryOrKey : $this->get($industryOrKey);

        $lines = [];
        $lines[] = '### INDUSTRY PROFILE & COMPLIANCE RULES:';
        $lines[] = '- Industry Classification: '.($profile['label_vi'] ?? $profile['name']).' (Key: '.$profile['key'].')';
        $lines[] = '- YMYL Sensitivity: '.(! empty($profile['is_ymyl']) ? 'HIGH (YMYL - Strict Factual & Safety Compliance Required)' : 'STANDARD');
        $lines[] = '- Verification Level: '.strtoupper((string) ($profile['verification_level'] ?? 'standard'));

        if (! empty($profile['content_rules']) && is_array($profile['content_rules'])) {
            $lines[] = '- Mandatory Industry Writing Rules:';
            foreach ($profile['content_rules'] as $rule) {
                $lines[] = '  * '.$rule;
            }
        }

        if (! empty($profile['terminology_guidelines'])) {
            $lines[] = '- Terminology Guidelines: '.$profile['terminology_guidelines'];
        }

        if (! empty($profile['sensitive_claims_to_control']) && is_array($profile['sensitive_claims_to_control'])) {
            $lines[] = '- Sensitive Claims to Strictly Control (DO NOT VIOLATE): '.implode('; ', $profile['sensitive_claims_to_control']);
        }

        if (! empty($profile['forbidden_fabrications']) && is_array($profile['forbidden_fabrications'])) {
            $lines[] = '- Forbidden Fabrications: '.implode('; ', $profile['forbidden_fabrications']);
        }

        if (! empty($profile['suitable_ctas']) && is_array($profile['suitable_ctas'])) {
            $lines[] = '- Recommended CTA Styles: '.implode(' | ', $profile['suitable_ctas']);
        }

        if (! empty($profile['disclaimer_template'])) {
            $lines[] = '- Required Advisory Note / Disclaimer Concept: '.$profile['disclaimer_template'];
        }

        return implode("\n", $lines);
    }

    /**
     * Built-in 12 standard Industry Profiles.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function builtInProfiles(): array
    {
        return [
            'healthcare' => [
                'key' => 'healthcare',
                'name' => 'Healthcare & Medical',
                'label_vi' => 'Y tế, Nha khoa, Phòng khám & Bệnh viện',
                'is_ymyl' => true,
                'verification_level' => 'strict',
                'keywords_signals' => [
                    'nha khoa', 'răng', 'implant', 'niềng răng', 'bọc răng sứ', 'phòng khám', 'bệnh viện',
                    'y tế', 'bác sĩ', 'điều trị', 'sức khỏe', 'chẩn đoán', 'phẫu thuật', 'dược', 'thuốc',
                    'khám bệnh', 'chuyên khoa', 'vật lý trị liệu', 'da liễu', 'nhãn khoa', 'healthcare', 'medical', 'dental',
                ],
                'content_rules' => [
                    'Cung cấp thông tin y khoa có căn cứ khoa học, giải thích rõ cơ chế hoặc phương pháp.',
                    'Nêu rõ chỉ định, chống chỉ định, rủi ro tiềm ẩn và giới hạn của phương pháp điều trị khi phù hợp.',
                    'Nhấn mạnh vai trò thăm khám và chỉ định trực tiếp từ bác sĩ chuyên khoa.',
                    'Phân biệt rõ thông tin giáo dục sức khỏe tham khảo với chẩn đoán/tư vấn y khoa cá nhân.',
                    'Tuyệt đối không cam kết kết quả điều trị 100%, không khẳng định khỏi bệnh vĩnh viễn không rủi ro.',
                    'Chỉ đề cập đến bằng cấp bác sĩ, giấy phép Bộ Y Tế/Sở Y Tế hoặc thiết bị khi có trong hồ sơ doanh nghiệp đã xác minh.',
                ],
                'terminology_guidelines' => 'Sử dụng thuật ngữ y khoa/nha khoa chuẩn xác nhưng luôn giải thích dễ hiểu cho bệnh nhân; giọng văn điềm tĩnh, khách quan, ân cần.',
                'common_search_intents' => ['informational', 'commercial', 'local'],
                'recommended_structures' => [
                    'default' => [
                        'Tổng quan & bản chất y khoa của vấn đề/phương pháp',
                        'Đối tượng chỉ định & các trường hợp cần lưu ý',
                        'Quy trình thăm khám / thực hiện tiêu chuẩn an toàn',
                        'Các yếu tố ảnh hưởng đến hiệu quả và chi phí tham khảo',
                        'Lưu ý chăm sóc, rủi ro cần theo dõi & câu hỏi thường gặp (FAQ)',
                    ],
                ],
                'sensitive_claims_to_control' => [
                    'Cam kết thành công 100% tuyệt đối không biến chứng',
                    'Chữa khỏi hoàn toàn vĩnh viễn mọi trường hợp',
                    'Không đau 100%, không cần bác sĩ thăm khám',
                    'Tự nhận là số 1 Việt Nam khi chưa có chứng nhận hợp pháp',
                ],
                'forbidden_fabrications' => [
                    'Không bịa tên bác sĩ, số giấy phép hành nghề hoặc số giấy phép hoạt động Sở Y Tế',
                    'Không bịa thống kê tỷ lệ thành công lâm sàng hoặc trích dẫn nghiên cứu y khoa giả',
                    'Không giả danh bác sĩ viết bài từ trải nghiệm cá nhân nếu không có dữ liệu tác giả xác thực',
                ],
                'suitable_ctas' => [
                    'Đặt lịch thăm khám và tư vấn cùng bác sĩ chuyên khoa',
                    'Liên hệ để được giải đáp tình trạng cụ thể',
                    'Tham khảo thông tin dịch vụ và quy trình thăm khám',
                ],
                'image_style_guidelines' => [
                    'style' => 'Clean, modern clinical environment, professional healthcare consultation, anatomical or educational medical illustration, bright natural lighting',
                    'avoid' => 'No gory medical imagery, no fake before/after patient photos presented as real proof, no text overlays, no fake clinic logos',
                ],
                'recommended_schemas' => ['Article', 'FAQPage', 'MedicalWebPage', 'LocalBusiness'],
                'disclaimer_template' => 'Thông tin trong bài viết mang tính chất tham khảo kiến thức y khoa, không thay thế cho việc chẩn đoán hoặc chỉ định điều trị trực tiếp từ bác sĩ chuyên khoa.',
            ],

            'legal' => [
                'key' => 'legal',
                'name' => 'Legal & Law Services',
                'label_vi' => 'Luật, Tư vấn Pháp lý & Thủ tục Hành chính',
                'is_ymyl' => true,
                'verification_level' => 'strict',
                'keywords_signals' => [
                    'luật', 'pháp lý', 'luật sư', 'thủ tục thành lập công ty', 'đăng ký kinh doanh',
                    'hợp đồng', 'tranh chấp', 'tố tụng', 'thuế', 'sở hữu trí tuệ', 'giấy phép con',
                    'tư vấn luật', 'văn phòng luật', 'công ty luật', 'nghị định', 'thông tư', 'legal', 'law',
                ],
                'content_rules' => [
                    'Phân biệt rõ quy định pháp luật chung và việc tư vấn áp dụng cho từng hồ sơ/tình huống cụ thể.',
                    'Trình bày rõ điều kiện, hồ sơ cần chuẩn bị, trình tự thủ tục, cơ quan tiếp nhận và thời hạn giải quyết.',
                    'Lưu ý rõ phạm vi áp dụng và khả năng văn bản pháp luật được sửa đổi, bổ sung theo từng thời điểm.',
                    'Tuyệt đối không tự sáng tác số hiệu điều luật, nghị định, thông tư hoặc mức phạt không có căn cứ.',
                    'Không cam kết kết quả thắng kiện hoặc xin giấy phép thành công 100% bất chấp điều kiện pháp lý.',
                ],
                'terminology_guidelines' => 'Ngôn ngữ pháp lý chuẩn mực, khúc chiết, mạch lạc, giải thích rõ các khái niệm pháp규 cho doanh nghiệp và cá nhân.',
                'common_search_intents' => ['informational', 'commercial', 'transactional'],
                'recommended_structures' => [
                    'default' => [
                        'Căn cứ pháp lý & phạm vi điều chỉnh cần lưu ý',
                        'Điều kiện và các yêu cầu cần đáp ứng theo quy định',
                        'Hồ sơ cần chuẩn bị chi tiết',
                        'Trình tự, thủ tục thực hiện từng bước & thời gian xử lý',
                        'Những sai sót thường gặp và giải pháp hỗ trợ pháp lý',
                    ],
                ],
                'sensitive_claims_to_control' => [
                    'Trích dẫn số điều khoản, số hiệu nghị định/luật tự chế không xác thực',
                    'Cam kết thắng kiện 100% hoặc bao đậu hồ sơ trái quy định',
                    'Khẳng định quy định có hiệu lực vĩnh viễn không thay đổi',
                ],
                'forbidden_fabrications' => [
                    'Không bịa đặt số hiệu Luật, Nghị định, Thông tư khi không có trong dữ liệu đầu vào',
                    'Không bịa thẻ luật sư, chứng chỉ hành nghề luật sư hoặc án lệ giả',
                ],
                'suitable_ctas' => [
                    'Liên hệ chuyên viên pháp lý để được rà soát hồ sơ chi tiết',
                    'Đăng ký nhận tư vấn thủ tục phù hợp với loại hình doanh nghiệp',
                ],
                'image_style_guidelines' => [
                    'style' => 'Professional corporate legal consultation office, business documents review, modern architecture, authoritative and trustworthy aesthetic',
                    'avoid' => 'No fake government seals, no misspelled text on legal books, no courtroom drama clichés',
                ],
                'recommended_schemas' => ['Article', 'FAQPage', 'Service', 'ProfessionalService'],
                'disclaimer_template' => 'Nội dung bài viết chỉ mang tính chất tham khảo thông tin pháp lý chung. Tùy thuộc vào thời điểm và hồ sơ thực tế, quy định áp dụng có thể khác nhau; vui lòng tham vấn chuyên gia pháp lý cho trường hợp cụ thể.',
            ],

            'real_estate' => [
                'key' => 'real_estate',
                'name' => 'Real Estate & Property',
                'label_vi' => 'Bất động sản, Chung cư & Đầu tư Địa ốc',
                'is_ymyl' => true,
                'verification_level' => 'high',
                'keywords_signals' => [
                    'bất động sản', 'căn hộ', 'chung cư', 'biệt thự', 'nhà phố', 'đất nền', 'dự án',
                    'chủ đầu tư', 'mặt bằng', 'pháp lý dự án', 'sổ hồng', 'cho thuê văn phòng', 'real estate', 'property',
                ],
                'content_rules' => [
                    'Phân tích khách quan vị trí kết nối, hệ thống tiện ích nội/ngoại khu, thiết kế mặt bằng và tiêu chuẩn bàn giao.',
                    'Đề cập rõ các yếu tố pháp lý cần kiểm tra (giấy phép xây dựng, quy hoạch, hợp đồng mua bán, sổ hồng).',
                    'Chỉ đưa ra mức giá bán/chính sách thanh toán cụ thể khi có dữ liệu từ doanh nghiệp; nếu không, trình bày dưới dạng khung tham khảo hoặc các yếu tố cấu thành giá.',
                    'Phân tích cân bằng cả tiềm năng gia tăng giá trị lẫn các rủi ro/lưu ý về dòng tiền, thanh khoản và tiến độ.',
                    'Tuyệt đối không tự bịa thông tin quy hoạch hạ tầng nhà nước hoặc cam kết lợi nhuận đầu tư chắc chắn.',
                ],
                'terminology_guidelines' => 'Sử dụng thuật ngữ bất động sản chuyên nghiệp (mật độ xây dựng, pháp lý sở hữu, thanh khoản, bàn giao, tiện ích nội khu).',
                'common_search_intents' => ['commercial', 'informational', 'transactional', 'local'],
                'recommended_structures' => [
                    'default' => [
                        'Tổng quan thị trường / phân khúc & nhu cầu thực tế',
                        'Đánh giá vị trí, hạ tầng kết nối & môi trường sống',
                        'Hệ thống tiện ích, tiêu chuẩn thiết kế & không gian sống',
                        'Tiêu chí pháp lý, mặt bằng giá tham khảo & bài toán tài chính',
                        'Kinh nghiệm lựa chọn & đánh giá cơ hội/rủi ro trước khi quyết định',
                    ],
                ],
                'sensitive_claims_to_control' => [
                    'Cam kết sinh lời chắc chắn 100% hoặc nhân đôi giá trị trong thời gian ngắn',
                    'Bịa đặt thông tin quy hoạch cầu đường, sân bay chưa được phê duyệt',
                    'Khẳng định đã có sổ hồng khi chưa có dữ liệu xác minh',
                ],
                'forbidden_fabrications' => [
                    'Không tự tạo bảng giá chính thức của chủ đầu tư nếu không có trong dữ liệu đầu vào',
                    'Không bịa văn bản phê duyệt quy hoạch 1/500 hoặc giấy phép xây dựng giả',
                ],
                'suitable_ctas' => [
                    'Nhận bộ tài liệu phân tích mặt bằng và thông tin pháp lý chi tiết',
                    'Đặt lịch tham quan thực tế và nhận tư vấn phương án tài chính',
                ],
                'image_style_guidelines' => [
                    'style' => 'Modern luxury residential architecture, bright interior design, landscaped urban living space, architectural photography',
                    'avoid' => 'No fake project logos, no distorted building geometry, no text watermarks',
                ],
                'recommended_schemas' => ['Article', 'FAQPage', 'Service', 'LocalBusiness'],
                'disclaimer_template' => 'Thông tin giá bán, chính sách và quy hoạch có thể thay đổi theo từng giai đoạn thị trường; người mua nên kiểm tra hồ sơ pháp lý thực tế trước khi giao dịch.',
            ],

            'technology' => [
                'key' => 'technology',
                'name' => 'Technology, Software & SaaS',
                'label_vi' => 'Công nghệ, Phần mềm, AI & Giải pháp SaaS B2B/B2C',
                'is_ymyl' => false,
                'verification_level' => 'standard',
                'keywords_signals' => [
                    'phần mềm', 'chatbot', 'ai', 'saas', 'crm', 'erp', 'ứng dụng', 'app', 'cloud',
                    'tự động hóa', 'automation', 'api', 'website', 'chuyển đổi số', 'công nghệ', 'software', 'platform',
                ],
                'content_rules' => [
                    'Tập trung giải quyết bài toán vận hành thực tế (pain points), hiệu suất và ROI cho người dùng/doanh nghiệp.',
                    'Phân tích rõ kiến trúc tính năng cốt lõi, các tình huống ứng dụng thực tế (Use Cases) và khả năng tích hợp (API, Webhook, hệ sinh thái).',
                    'Trình bày quy trình triển khai từng bước rõ ràng, khách quan cả ưu điểm lẫn giới hạn kỹ thuật.',
                    'So sánh tiêu chí lựa chọn giải pháp công nghệ dựa trên quy mô doanh nghiệp, bảo mật dữ liệu và khả năng mở rộng.',
                    'Không bịa đặt thông số benchmark kỹ thuật, chứng chỉ bảo mật (ISO/SOC2) nếu doanh nghiệp chưa cung cấp.',
                ],
                'terminology_guidelines' => 'Ngôn ngữ công nghệ hiện đại, chuẩn xác, giải thích rõ các thuật ngữ kỹ thuật (API, Omnichannel, NLP, Workflow, SLA) gắn liền với lợi ích kinh doanh.',
                'common_search_intents' => ['commercial', 'informational', 'transactional'],
                'recommended_structures' => [
                    'default' => [
                        'Bối cảnh bài toán vận hành & lý do cần giải pháp công nghệ',
                        'Cơ chế hoạt động & các tính năng cốt lõi cần có',
                        'Các Use Cases ứng dụng thực tế mang lại hiệu quả cao',
                        'Tiêu chí đánh giá, khả năng tích hợp & bảo mật dữ liệu',
                        'Quy trình triển khai tối ưu & hướng dẫn bắt đầu',
                    ],
                ],
                'sensitive_claims_to_control' => [
                    'Cam kết uptime 100% tuyệt đối không bao giờ gián đoạn',
                    'Tự nhận đạt chứng chỉ ISO 27001, SOC2, GDPR khi chưa xác minh',
                ],
                'forbidden_fabrications' => [
                    'Không bịa tên khách hàng doanh nghiệp lớn (case study giả) khi chưa có dữ liệu xác thực',
                    'Không tự tạo bảng giá gói cước sai lệch với thông tin sản phẩm đã khai báo',
                ],
                'suitable_ctas' => [
                    'Đăng ký trải nghiệm bản Demo hoặc dùng thử giải pháp',
                    'Liên hệ đội ngũ kỹ thuật để được tư vấn kịch bản tích hợp phù hợp',
                ],
                'image_style_guidelines' => [
                    'style' => 'Modern B2B tech workspace, clean abstract digital workflow visualization, professionals collaborating with laptops in sleek office',
                    'avoid' => 'No gibberish UI text on screens, no sci-fi robots clichés unless requested, no fake brand logos',
                ],
                'recommended_schemas' => ['Article', 'FAQPage', 'SoftwareApplication', 'Service'],
                'disclaimer_template' => null,
            ],

            'ecommerce' => [
                'key' => 'ecommerce',
                'name' => 'E-commerce & Consumer Products',
                'label_vi' => 'Thương mại điện tử, Bán lẻ & Thiết bị Gia dụng',
                'is_ymyl' => false,
                'verification_level' => 'standard',
                'keywords_signals' => [
                    'máy lọc không khí', 'máy hút bụi', 'điện máy', 'gia dụng', 'mua', 'review sản phẩm',
                    'kinh nghiệm mua', 'so sánh', 'chính hãng', 'bảo hành', 'cửa hàng', 'shop', 'ecommerce', 'sản phẩm',
                ],
                'content_rules' => [
                    'Phân tích rõ công dụng thực tế, cơ chế hoạt động, thông số kỹ thuật quan trọng và đối tượng/không gian sử dụng phù hợp.',
                    'Hướng dẫn chi tiết tiêu chí chọn mua sản phẩm theo nhu cầu thực tế (diện tích phòng, công suất, màng lọc, độ ồn, chi phí thay thế linh kiện).',
                    'So sánh ưu/nhược điểm giữa các dòng sản phẩm hoặc phân khúc một cách khách quan, giúp người mua dễ ra quyết định.',
                    'Nêu rõ lưu ý sử dụng, bảo dưỡng định kỳ và chính sách mua hàng/bảo hành khi có dữ liệu.',
                    'Không bịa thông số kỹ thuật hoặc chứng nhận kiểm định chất lượng không có thật.',
                ],
                'terminology_guidelines' => 'Ngôn từ gần gũi, thiết thực, tập trung vào trải nghiệm sử dụng thực tế và giải thích thông số kỹ thuật dễ hiểu cho người tiêu dùng.',
                'common_search_intents' => ['commercial', 'transactional', 'informational'],
                'recommended_structures' => [
                    'default' => [
                        'Nhu cầu thực tế & lợi ích thiết thực của sản phẩm',
                        'Các thông số và công nghệ cốt lõi cần quan tâm khi chọn mua',
                        'Hướng dẫn lựa chọn sản phẩm phù hợp với từng nhu cầu & ngân sách',
                        'Kinh nghiệm sử dụng bền bỉ, hiệu quả & lưu ý bảo dưỡng',
                        'Giải đáp thắc mắc thường gặp trước khi đặt mua (FAQ)',
                    ],
                ],
                'sensitive_claims_to_control' => [
                    'Khẳng định sản phẩm diệt 100% virus gây bệnh y khoa khi chưa có chứng nhận',
                    'Bịa đặt giải thưởng tiêu dùng hoặc đánh giá khách hàng giả mạo',
                ],
                'forbidden_fabrications' => [
                    'Không tự bịa mã model sản phẩm, mức giá khuyến mãi hoặc thời hạn bảo hành khi chưa được cung cấp',
                ],
                'suitable_ctas' => [
                    'Tham khảo danh mục sản phẩm chính hãng và nhận tư vấn mẫu phù hợp',
                    'Liên hệ để kiểm tra tình trạng còn hàng và chính sách bảo hành',
                ],
                'image_style_guidelines' => [
                    'style' => 'High-end lifestyle product photography in a clean modern living room, natural daylight, minimalist home interior',
                    'avoid' => 'No distorted product buttons/labels, no unreadable text on packaging, no fake brand logos',
                ],
                'recommended_schemas' => ['Article', 'FAQPage', 'Product'],
                'disclaimer_template' => null,
            ],

            'travel' => [
                'key' => 'travel',
                'name' => 'Travel, Hospitality & Tourism',
                'label_vi' => 'Du lịch, Khách sạn, Nghỉ dưỡng & Trải nghiệm',
                'is_ymyl' => false,
                'verification_level' => 'standard',
                'keywords_signals' => [
                    'du lịch', 'đà lạt', 'khách sạn', 'resort', 'homestay', 'tour', 'lịch trình',
                    'kinh nghiệm du lịch', 'vé máy bay', 'điểm đến', 'check-in', 'ẩm thực địa phương', 'travel',
                ],
                'content_rules' => [
                    'Cung cấp thông tin thực tế, hữu ích về thời điểm lý tưởng, phương tiện di chuyển, lưu trú, điểm tham quan và ẩm thực đặc trưng.',
                    'Gợi ý lịch trình hợp lý theo từng nhóm du khách (gia đình, cặp đôi, nhóm bạn, nghỉ dưỡng) giúp tối ưu thời gian di chuyển.',
                    'Chia sẻ các khoản chi phí dự kiến dưới dạng ước tính tham khảo và nhắc người đọc kiểm tra giá vé/dịch vụ cập nhật theo mùa.',
                    'Bổ sung các lưu ý an toàn, văn hóa địa phương, thời tiết và hành trang cần chuẩn bị.',
                ],
                'terminology_guidelines' => 'Giọng văn truyền cảm hứng, sinh động, giàu tính hướng dẫn thực tế nhưng rõ ràng, súc tích.',
                'common_search_intents' => ['informational', 'commercial', 'local'],
                'recommended_structures' => [
                    'default' => [
                        'Thời điểm lý tưởng & tổng quan đặc trưng của điểm đến',
                        'Hướng dẫn phương tiện di chuyển & kinh nghiệm chọn nơi lưu trú',
                        'Gợi ý các điểm tham quan, trải nghiệm & ẩm thực nổi bật',
                        'Lịch trình tham khảo hợp lý & ước tính chi phí cần chuẩn bị',
                        'Những lưu ý quan trọng để chuyến đi trọn vẹn & an toàn',
                    ],
                ],
                'sensitive_claims_to_control' => [
                    'Khẳng định giá vé cố định tuyệt đối không đổi quanh năm',
                ],
                'forbidden_fabrications' => [
                    'Không bịa địa danh, tuyến đường hoặc số điện thoại đặt dịch vụ không tồn tại',
                ],
                'suitable_ctas' => [
                    'Tham khảo các gói dịch vụ lưu trú / lịch trình tour phù hợp',
                    'Liên hệ tư vấn viên để lên kế hoạch chuyến đi theo nhu cầu riêng',
                ],
                'image_style_guidelines' => [
                    'style' => 'Breathtaking scenic travel photography, misty pine forests, cozy boutique resort atmosphere, golden hour natural lighting',
                    'avoid' => 'No text overlays, no watermarks, no unrealistic AI artifacts',
                ],
                'recommended_schemas' => ['Article', 'FAQPage'],
                'disclaimer_template' => null,
            ],

            'finance' => [
                'key' => 'finance',
                'name' => 'Finance, Banking, Accounting & Insurance',
                'label_vi' => 'Tài chính, Kế toán, Ngân hàng & Bảo hiểm',
                'is_ymyl' => true,
                'verification_level' => 'strict',
                'keywords_signals' => [
                    'tài chính', 'kế toán', 'kiểm toán', 'ngân hàng', 'vay vốn', 'lãi suất', 'bảo hiểm',
                    'đầu tư', 'chứng khoán', 'quyết toán thuế', 'báo cáo tài chính', 'tín dụng', 'finance',
                ],
                'content_rules' => [
                    'Trình bày nguyên tắc tài chính, kế toán, thuế hoặc sản phẩm tài chính minh bạch, có cơ sở.',
                    'Nêu rõ điều kiện áp dụng, công thức tính toán tham khảo, biểu phí và các rủi ro tài chính đi kèm.',
                    'Không đưa ra lời khuyên đầu tư cam kết sinh lời chắc chắn hoặc xúi giục vay nợ vượt khả năng chi trả.',
                    'Luôn phân biệt thông tin kiến thức tài chính với tư vấn tài chính/kế toán chuyên biệt.',
                ],
                'terminology_guidelines' => 'Chính xác, minh bạch, trung lập, giải thích rõ các chỉ số và điều khoản tài chính.',
                'common_search_intents' => ['informational', 'commercial', 'transactional'],
                'recommended_structures' => [
                    'default' => [
                        'Khái niệm cốt lõi & tầm quan trọng trong quản trị tài chính',
                        'Điều kiện, quy trình & cách thức tính toán/thực hiện',
                        'Phân tích lợi ích, chi phí và các rủi ro cần kiểm soát',
                        'Những sai lầm phổ biến và kinh nghiệm tối ưu an toàn',
                        'Câu hỏi thường gặp (FAQ)',
                    ],
                ],
                'sensitive_claims_to_control' => [
                    'Cam kết lợi nhuận đầu tư cố định không rủi ro',
                    'Cam kết duyệt vay 100% bất chấp nợ xấu',
                ],
                'forbidden_fabrications' => [
                    'Không bịa biểu lãi suất ngân hàng hoặc quy định thuế không có căn cứ',
                ],
                'suitable_ctas' => [
                    'Liên hệ chuyên viên tư vấn tài chính / kế toán để được hỗ trợ chi tiết',
                ],
                'image_style_guidelines' => [
                    'style' => 'Professional financial advisory meeting, clean modern corporate office, analytical business environment',
                    'avoid' => 'No fake currency piles, no unreadable chart text, no fake bank logos',
                ],
                'recommended_schemas' => ['Article', 'FAQPage', 'FinancialService'],
                'disclaimer_template' => 'Nội dung bài viết chỉ mang tính chất cung cấp thông tin tham khảo, không cấu thành lời khuyên đầu tư hay tư vấn tài chính cá nhân hóa.',
            ],

            'education' => [
                'key' => 'education',
                'name' => 'Education, Training & EdTech',
                'label_vi' => 'Giáo dục, Đào tạo, Khóa học & Du học',
                'is_ymyl' => false,
                'verification_level' => 'standard',
                'keywords_signals' => [
                    'giáo dục', 'đào tạo', 'khóa học', 'ielts', 'tiếng anh', 'du học', 'trường học',
                    'học viện', 'luyện thi', 'chứng chỉ', 'kỹ năng', 'edtech', 'education',
                ],
                'content_rules' => [
                    'Xây dựng lộ trình học tập rõ ràng theo từng trình độ đầu vào và mục tiêu đầu ra thực tế.',
                    'Phân tích phương pháp học, tài liệu tham khảo và tiêu chí đánh giá chất lượng đào tạo.',
                    'Không cam kết bao đậu 100% không cần nỗ lực học tập hoặc bịa đặt chứng nhận quốc tế.',
                ],
                'terminology_guidelines' => 'Rõ ràng, khích lệ, có tính sư phạm và hướng dẫn thực hành cao.',
                'common_search_intents' => ['informational', 'commercial'],
                'recommended_structures' => [
                    'default' => [
                        'Mục tiêu & đối tượng phù hợp với chương trình/kiến thức',
                        'Lộ trình & nội dung trọng tâm cần nắm vững',
                        'Phương pháp rèn luyện hiệu quả & tài liệu tham khảo',
                        'Tiêu chí lựa chọn môi trường đào tạo uy tín',
                        'Câu hỏi thường gặp (FAQ)',
                    ],
                ],
                'sensitive_claims_to_control' => [
                    'Cam kết bao đậu 100% không cần học',
                ],
                'forbidden_fabrications' => [
                    'Không bịa tỷ lệ học viên đỗ đạt hoặc bằng cấp giảng viên chưa xác minh',
                ],
                'suitable_ctas' => [
                    'Đăng ký kiểm tra trình độ đầu vào và nhận tư vấn lộ trình học phù hợp',
                ],
                'image_style_guidelines' => [
                    'style' => 'Modern bright classroom or study space, engaged students and mentor, collaborative learning environment',
                    'avoid' => 'No text on blackboards/books, no fake certificates',
                ],
                'recommended_schemas' => ['Article', 'FAQPage', 'Course'],
                'disclaimer_template' => null,
            ],

            'manufacturing' => [
                'key' => 'manufacturing',
                'name' => 'Manufacturing, Industrial & B2B Supply',
                'label_vi' => 'Sản xuất, Công nghiệp, Vật liệu & Cơ khí B2B',
                'is_ymyl' => false,
                'verification_level' => 'standard',
                'keywords_signals' => [
                    'sản xuất', 'nhà máy', 'gia công', 'cơ khí', 'vật liệu xây dựng', 'công nghiệp',
                    'thiết bị công nghiệp', 'b2b', 'oem', 'odm', 'dây chuyền', 'manufacturing',
                ],
                'content_rules' => [
                    'Trình bày rõ đặc tính kỹ thuật, quy trình sản xuất/gia công, tiêu chuẩn kiểm soát chất lượng (QC) và ứng dụng thực tế.',
                    'Nhấn mạnh các yếu tố năng lực cung ứng, tiến độ giao hàng và tiêu chuẩn an toàn kỹ thuật.',
                    'Không tự bịa chứng nhận ISO, ASTM, JIS nếu hồ sơ doanh nghiệp chưa cung cấp.',
                ],
                'terminology_guidelines' => 'Kỹ thuật chuẩn xác, chuyên nghiệp theo văn phong B2B công nghiệp.',
                'common_search_intents' => ['commercial', 'informational', 'transactional'],
                'recommended_structures' => [
                    'default' => [
                        'Đặc điểm kỹ thuật & vai trò trong sản xuất/công trình',
                        'Phân loại, quy cách & ứng dụng thực tế',
                        'Quy trình gia công / kiểm soát chất lượng tiêu chuẩn',
                        'Tiêu chí lựa chọn nhà cung cấp & báo giá tham khảo',
                        'Câu hỏi thường gặp (FAQ)',
                    ],
                ],
                'sensitive_claims_to_control' => [
                    'Tự nhận chứng chỉ chất lượng quốc tế chưa được xác minh',
                ],
                'forbidden_fabrications' => [
                    'Không bịa thông số chịu lực/kỹ thuật sai lệch gây mất an toàn công trình',
                ],
                'suitable_ctas' => [
                    'Liên hệ phòng kinh doanh B2B để nhận hồ sơ năng lực và báo giá theo khối lượng',
                ],
                'image_style_guidelines' => [
                    'style' => 'Clean modern industrial facility, precision engineering equipment, high quality materials close-up',
                    'avoid' => 'No unsafe working conditions, no text overlays',
                ],
                'recommended_schemas' => ['Article', 'FAQPage', 'Product', 'Service'],
                'disclaimer_template' => null,
            ],

            'beauty' => [
                'key' => 'beauty',
                'name' => 'Beauty, Aesthetics & Cosmetics',
                'label_vi' => 'Làm đẹp, Thẩm mỹ viện, Spa & Mỹ phẩm',
                'is_ymyl' => true,
                'verification_level' => 'high',
                'keywords_signals' => [
                    'thẩm mỹ', 'spa', 'làm đẹp', 'chăm sóc da', 'mỹ phẩm', 'trẻ hóa', 'triệt lông',
                    'trị mụn', 'trị nám', 'skincare', 'serum', 'kem chống nắng', 'beauty', 'aesthetics',
                ],
                'content_rules' => [
                    'Giải thích cơ chế chăm sóc/làm đẹp khoa học, thành phần hoạt chất hoặc công nghệ thẩm mỹ một cách trung thực.',
                    'Nêu rõ tình trạng da/cơ địa phù hợp, quy trình chăm sóc và các phản ứng kích ứng cần lưu ý.',
                    'Không cam kết hiệu quả lột xác tức thì 100% hoặc thổi phồng công dụng mỹ phẩm như thuốc chữa bệnh.',
                ],
                'terminology_guidelines' => 'Tinh tế, khoa học, trung thực, tôn trọng chuẩn mực an toàn da liễu/thẩm mỹ.',
                'common_search_intents' => ['informational', 'commercial', 'local'],
                'recommended_structures' => [
                    'default' => [
                        'Nguyên nhân & cơ chế khoa học của vấn đề làn da / thẩm mỹ',
                        'Giải pháp / thành phần / công nghệ chăm sóc phù hợp',
                        'Quy trình thực hiện & hướng dẫn chăm sóc đúng cách',
                        'Những lưu ý an toàn để tránh kích ứng hoặc biến chứng',
                        'Câu hỏi thường gặp (FAQ)',
                    ],
                ],
                'sensitive_claims_to_control' => [
                    'Cam kết trị sạch nám/mụn vĩnh viễn 100% sau 1 lần',
                    'Quảng cáo mỹ phẩm có tác dụng thay thế thuốc chữa bệnh',
                ],
                'forbidden_fabrications' => [
                    'Không bịa chứng nhận FDA/CE khi doanh nghiệp chưa cung cấp bằng chứng',
                ],
                'suitable_ctas' => [
                    'Đặt lịch soi da / tư vấn liệu trình phù hợp với tình trạng thực tế',
                ],
                'image_style_guidelines' => [
                    'style' => 'Elegant serene spa aesthetic, natural glowing skin care concept, botanical and minimalist luxury composition',
                    'avoid' => 'No fake before/after medical claims, no text on cosmetic bottles',
                ],
                'recommended_schemas' => ['Article', 'FAQPage', 'HealthAndBeautyBusiness'],
                'disclaimer_template' => 'Hiệu quả chăm sóc và làm đẹp phụ thuộc vào cơ địa và tình trạng da của từng người; nên tham khảo ý kiến chuyên gia trước khi áp dụng liệu trình chuyên sâu.',
            ],

            'food_beverage' => [
                'key' => 'food_beverage',
                'name' => 'Food, Beverage & Hospitality',
                'label_vi' => 'Nhà hàng, Ẩm thực, Thực phẩm & Đồ uống (F&B)',
                'is_ymyl' => false,
                'verification_level' => 'standard',
                'keywords_signals' => [
                    'nhà hàng', 'ẩm thực', 'món ăn', 'thực phẩm', 'đồ uống', 'cà phê', 'trà',
                    'bánh', 'đặc sản', 'dinh dưỡng', 'nguyên liệu', 'f&b', 'food', 'restaurant',
                ],
                'content_rules' => [
                    'Mô tả hương vị, nguồn gốc nguyên liệu, quy trình chế biến/bảo quản và giá trị trải nghiệm ẩm thực chân thực.',
                    'Đề cao tiêu chuẩn vệ sinh an toàn thực phẩm và cách thưởng thức/kết hợp món ăn.',
                    'Không gán ghép công dụng chữa bệnh y khoa cho thực phẩm thông thường.',
                ],
                'terminology_guidelines' => 'Hấp dẫn, giàu cảm xúc ẩm thực, rõ ràng về nguồn gốc và chất lượng nguyên liệu.',
                'common_search_intents' => ['informational', 'commercial', 'local'],
                'recommended_structures' => [
                    'default' => [
                        'Nét đặc trưng & giá trị của món ăn / sản phẩm thực phẩm',
                        'Tiêu chí tuyển chọn nguyên liệu & quy trình chế biến chuẩn vị',
                        'Hướng dẫn thưởng thức, bảo quản & kết hợp thực đơn',
                        'Kinh nghiệm lựa chọn địa chỉ / thương hiệu chất lượng',
                        'Câu hỏi thường gặp (FAQ)',
                    ],
                ],
                'sensitive_claims_to_control' => [
                    'Thổi phồng thực phẩm có khả năng điều trị bệnh lý y khoa',
                ],
                'forbidden_fabrications' => [
                    'Không bịa giấy chứng nhận VSATTP hoặc nguồn gốc hữu cơ (Organic) chưa xác minh',
                ],
                'suitable_ctas' => [
                    'Đặt bàn trước hoặc tham khảo thực đơn / sản phẩm tươi ngon hôm nay',
                ],
                'image_style_guidelines' => [
                    'style' => 'Appetizing professional food photography, warm natural lighting, fresh ingredients on rustic wooden table',
                    'avoid' => 'No text menus, no watermarks',
                ],
                'recommended_schemas' => ['Article', 'FAQPage', 'FoodEstablishment'],
                'disclaimer_template' => null,
            ],

            'other' => self::defaultProfileTemplate('other'),
        ];
    }

    /**
     * Default universal profile template for general or custom industries.
     *
     * @return array<string, mixed>
     */
    protected static function defaultProfileTemplate(string $key = 'other'): array
    {
        return [
            'key' => $key,
            'name' => 'General Business & Services',
            'label_vi' => 'Doanh nghiệp & Dịch vụ Đa ngành',
            'is_ymyl' => false,
            'verification_level' => 'standard',
            'keywords_signals' => [],
            'content_rules' => [
                'Cung cấp thông tin chuyên sâu, chính xác, giải quyết trực diện nhu cầu tìm kiếm của độc giả.',
                'Trình bày mạch lạc, khách quan, có ví dụ hoặc hướng dẫn thực tế.',
                'Chỉ sử dụng thông tin doanh nghiệp đã được xác minh, không tự suy diễn số liệu hoặc chứng nhận.',
            ],
            'terminology_guidelines' => 'Chuyên nghiệp, rõ ràng, phù hợp với đối tượng khách hàng mục tiêu của doanh nghiệp.',
            'common_search_intents' => ['informational', 'commercial', 'transactional'],
            'recommended_structures' => [
                'default' => [
                    'Tổng quan vấn đề & nhu cầu thực tế của khách hàng',
                    'Phân tích chi tiết các khía cạnh / giải pháp trọng tâm',
                    'Quy trình thực hiện hoặc tiêu chí đánh giá lựa chọn',
                    'Lưu ý thực tiễn giúp tối ưu hiệu quả và chi phí',
                    'Câu hỏi thường gặp (FAQ)',
                ],
            ],
            'sensitive_claims_to_control' => [
                'Cam kết tuyệt đối 100% không có căn cứ xác thực',
                'Tự nhận vị thế dẫn đầu thị trường khi không có bằng chứng',
            ],
            'forbidden_fabrications' => [
                'Không bịa đặt số liệu doanh nghiệp, giải thưởng, chứng chỉ hoặc đánh giá khách hàng giả',
            ],
            'suitable_ctas' => [
                'Liên hệ đội ngũ chuyên viên để được tư vấn giải pháp phù hợp',
            ],
            'image_style_guidelines' => [
                'style' => 'Professional editorial photography, modern business context, clean composition, natural lighting',
                'avoid' => 'No text overlays, no fake logos, no watermarks',
            ],
            'recommended_schemas' => ['Article', 'FAQPage'],
            'disclaimer_template' => null,
        ];
    }
}
