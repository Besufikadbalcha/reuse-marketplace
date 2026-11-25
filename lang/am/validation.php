<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines contain the default error messages used by
    | the validator class. Some of these rules have multiple versions such
    | as the size rules. Feel free to tweak each of these messages here.
    |
    */

    'accepted' => ':attribute መስኩ መቀበል አለበት።',
    'accepted_if' => ':attribute መስኩ :other :value ሲሆን መቀበል አለበት።',
    'active_url' => ':attribute መስኩ ትክክለኛ URL መሆን አለበት።',
    'after' => ':attribute መስኩ ከ:date በኋላ ያለ ቀን መሆን አለበት።',
    'after_or_equal' => ':attribute መስኩ ከ:date በኋላ ወይም እኩል ያለ ቀን መሆን አለበት።',
    'alpha' => ':attribute መስኩ ፊደላት ብቻ መያዝ አለበት።',
    'alpha_dash' => ':attribute መስኩ ፊደላት፣ ቁጥሮች፣ ሰረዞችና የታችኛ መስመሮች ብቻ መያዝ አለበት።',
    'alpha_num' => ':attribute መስኩ ፊደላትንና ቁጥሮችን ብቻ መያዝ አለበት።',
    'any_of' => ':attribute መስኩ ልክ አይደለም።',
    'array' => ':attribute መስኩ ዝርዝር መሆን አለበት።',
    'array_keys' => ':attribute መስኩ ከሚከተሉት ቁልፎች ብቻ ሊይዝ ይችላል። :values',
    'ascii' => ':attribute መስኩ ነጠላ-ባይት ፊደላት፣ ቁጥሮችና ምልክቶች ብቻ መያዝ አለበት።',
    'base64' => ':attribute መስኩ ትክክለኛ Base64 ገመድ መሆን አለበት።',
    'before' => ':attribute መስኩ ከ:date በፊት ያለ ቀን መሆን አለበት።',
    'before_or_equal' => ':attribute መስኩ ከ:date በፊት ወይም እኩል ያለ ቀን መሆን አለበት።',
    'between' => [
        'array' => ':attribute መስኩ ከ:min እስከ :max ንጥሎች መያዝ አለበት።',
        'file' => ':attribute መስኩ ከ:min እስከ :max ኪሎባይት መሆን አለበት።',
        'numeric' => ':attribute መስኩ ከ:min እና :max መካከል መሆን አለበት።',
        'string' => ':attribute መስኩ ከ:min እስከ :max ፊደላት መሆን አለበት።',
    ],
    'boolean' => ':attribute መስኩ እውነት ወይም ሐሰት መሆን አለበት።',
    'can' => ':attribute መስኩ ያልተፈቀደ እሴት ይዟል።',
    'confirmed' => ':attribute መስኩ ማረጋገጫው አይዛመድም።',
    'contains' => ':attribute መስኩ አስፈላጊውን እሴት አልያዘም።',
    'current_password' => 'የገቡት የይለፍ ቃል ትክክል አይደለም።',
    'date' => ':attribute መስኩ ትክክለኛ ቀን መሆን አለበት።',
    'date_equals' => ':attribute መስኩ ከ:date ጋር እኩል ያለ ቀን መሆን አለበት።',
    'date_format' => ':attribute መስኩ ከ:format ቅርጸት ጋር መዛመድ አለበት።',
    'decimal' => ':attribute መስኩ በ:decimal የአስርዮሽ ቦታዎች መገለጽ አለበት።',
    'declined' => ':attribute መስኩ መክድ አለበት።',
    'declined_if' => ':attribute መስኩ :other :value ሲሆን መክድ አለበት።',
    'different' => ':attribute መስኩና :other የተለያዩ መሆን አለባቸው።',
    'digits' => ':attribute መስኩ :digits አሃዞች መሆን አለበት።',
    'digits_between' => ':attribute መስኩ ከ:min እስከ :max አሃዞች መሆን አለበት።',
    'dimensions' => ':attribute መስኩ የምስሉ መጠኖች ልክ አይደሉም።',
    'distinct' => ':attribute መስኩ ደጋግሞ የሚገኝ እሴት አለው።',
    'doesnt_contain' => ':attribute መስኩ ከሚከተሉት ውስጥ ማንኛውንም መያዝ የለበትም። :values',
    'doesnt_end_with' => ':attribute መስኩ ከሚከተሉት ውስጥ በአንዱ መጨረሻ መሆን የለበትም። :values',
    'doesnt_start_with' => ':attribute መስኩ ከሚከተሉት ውስጥ በአንዱ መጀመር የለበትም። :values',
    'email' => ':attribute መስኩ ትክክለኛ የኢሜይል አድራሻ መሆን አለበት።',
    'encoding' => ':attribute መስኩ በ:encoding መቅዳት አለበት።',
    'ends_with' => ':attribute መስኩ ከሚከተሉት ውስጥ በአንዱ መጨረሻ መሆን አለበት። :values',
    'enum' => 'የተመረጠው :attribute ልክ አይደለም።',
    'exists' => 'የተመረጠው :attribute ልክ አይደለም።',
    'extensions' => ':attribute መስኩ ከሚከተሉት ውስጥ አንዱን ቅጥያ መያዝ አለበት። :values',
    'file' => ':attribute መስኩ ፋይል መሆን አለበት።',
    'filled' => ':attribute መስኩ እሴት መያዝ አለበት።',
    'gt' => [
        'array' => ':attribute መስኩ ከ:value በላይ ንጥሎች መያዝ አለበት።',
        'file' => ':attribute መስኩ ከ:value ኪሎባይት በላይ መሆን አለበት።',
        'numeric' => ':attribute መስኩ ከ:value በላይ መሆን አለበት።',
        'string' => ':attribute መስኩ ከ:value ፊደላት በላይ መሆን አለበት።',
    ],
    'gte' => [
        'array' => ':attribute መስኩ :value ወይም ከዚያ በላይ ንጥሎች መያዝ አለበት።',
        'file' => ':attribute መስኩ ከ:value ኪሎባይት ወይም ከዚያ በላይ መሆን አለበት።',
        'numeric' => ':attribute መስኩ ከ:value ወይም ከዚያ በላይ መሆን አለበት።',
        'string' => ':attribute መስኩ :value ወይም ከዚያ በላይ ፊደላት መሆን አለበት።',
    ],
    'hex_color' => ':attribute መስኩ ትክክለኛ የሄክስ ቀለም መሆን አለበት።',
    'image' => ':attribute መስኩ ምስል መሆን አለበት።',
    'in' => 'የተመረጠው :attribute ልክ አይደለም።',
    'in_array' => ':attribute መስኩ በ:other ውስጥ መገኘት አለበት።',
    'in_array_keys' => ':attribute መስኩ ከሚከተሉት ቁልፎች ውስጥ ቢያንስ አንዱን መያዝ አለበት። :values',
    'integer' => ':attribute መስኩ ኢንቲጀር መሆን አለበት።',
    'ip' => ':attribute መስኩ ትክክለኛ የአይፒ አድራሻ መሆን አለበት።',
    'ipv4' => ':attribute መስኩ ትክክለኛ የIPv4 አድራሻ መሆን አለበት።',
    'ipv6' => ':attribute መስኩ ትክክለኛ የIPv6 አድራሻ መሆን አለበት።',
    'json' => ':attribute መስኩ ትክክለኛ የJSON ገመድ መሆን አለበት።',
    'list' => ':attribute መስኩ ዝርዝር መሆን አለበት።',
    'lowercase' => ':attribute መስኩ በትንሽ ፊደላት መሆን አለበት።',
    'lt' => [
        'array' => ':attribute መስኩ ከ:value ያነሱ ንጥሎች መያዝ አለበት።',
        'file' => ':attribute መስኩ ከ:value ኪሎባይት ያነሰ መሆን አለበት።',
        'numeric' => ':attribute መስኩ ከ:value ያነሰ መሆን አለበት።',
        'string' => ':attribute መስኩ ከ:value ፊደላት ያነሰ መሆን አለበት።',
    ],
    'lte' => [
        'array' => ':attribute መስኩ ከ:value በላይ ንጥሎች መያዝ የለበትም።',
        'file' => ':attribute መስኩ ከ:value ኪሎባይት ወይም ያነሰ መሆን አለበት።',
        'numeric' => ':attribute መስኩ ከ:value ወይም ያነሰ መሆን አለበት።',
        'string' => ':attribute መስኩ :value ወይም ያነሰ ፊደላት መሆን አለበት።',
    ],
    'mac_address' => ':attribute መስኩ ትክክለኛ የMAC አድራሻ መሆን አለበት።',
    'max' => [
        'array' => ':attribute መስኩ ከ:max በላይ ንጥሎች መያዝ የለበትም።',
        'file' => ':attribute መስኩ ከ:max ኪሎባይት መብለጥ የለበትም።',
        'numeric' => ':attribute መስኩ ከ:max መብለጥ የለበትም።',
        'string' => ':attribute መስኩ ከ:max ፊደላት መብለጥ የለበትም።',
    ],
    'max_digits' => ':attribute መስኩ ከ:max አሃዞች መብለጥ የለበትም።',
    'mimes' => ':attribute መስኩ ከሚከተሉት ዓይነቶች ውስጥ የሆነ ፋይል መሆን አለበት። :values',
    'mimetypes' => ':attribute መስኩ ከሚከተሉት ዓይነቶች ውስጥ የሆነ ፋይል መሆን አለበት። :values',
    'min' => [
        'array' => ':attribute መስኩ ቢያንስ :min ንጥሎች መያዝ አለበት።',
        'file' => ':attribute መስኩ ቢያንስ :min ኪሎባይት መሆን አለበት።',
        'numeric' => ':attribute መስኩ ቢያንስ :min መሆን አለበት።',
        'string' => ':attribute መስኩ ቢያንስ :min ፊደላት መሆን አለበት።',
    ],
    'min_digits' => ':attribute መስኩ ቢያንስ :min አሃዞች መሆን አለበት።',
    'missing' => ':attribute መስኩ መገኘት የለበትም።',
    'missing_if' => ':attribute መስኩ :other :value ሲሆን መገኘት የለበትም።',
    'missing_unless' => ':attribute መስኩ :other :value ካልሆነ በቀር መገኘት የለበትም።',
    'missing_with' => ':values በሚገኝበት ጊዜ :attribute መስኩ መገኘት የለበትም።',
    'missing_with_all' => ':values በሚገኙበት ጊዜ :attribute መስኩ መገኘት የለበትም።',
    'multiple_of' => ':attribute መስኩ የ:value ብዜት መሆን አለበት።',
    'not_in' => 'የተመረጠው :attribute ልክ አይደለም።',
    'not_regex' => ':attribute መስኩ ቅርጸት ልክ አይደለም።',
    'numeric' => ':attribute መስኩ ቁጥር መሆን አለበት።',
    'password' => [
        'letters' => ':attribute መስኩ ቢያንስ አንድ ፊደል መያዝ አለበት።',
        'mixed' => ':attribute መስኩ ቢያንስ አንድ ትልቅና አንድ ትንሽ ፊደል መያዝ አለበት።',
        'numbers' => ':attribute መስኩ ቢያንስ አንድ ቁጥር መያዝ አለበት።',
        'symbols' => ':attribute መስኩ ቢያንስ አንድ ምልክት መያዝ አለበት።',
        'uncompromised' => 'የተሰጠው :attribute በመረጃ ፍሳሽ ውስጥ ታይቷል። እባክዎ ሌላ :attribute ይምረጡ።',
    ],
    'present' => ':attribute መስኩ መገኘት አለበት።',
    'present_if' => ':attribute መስኩ :other :value ሲሆን መገኘት አለበት።',
    'present_unless' => ':attribute መስኩ :other :value ካልሆነ በቀር መገኘት አለበት።',
    'present_with' => ':values በሚገኝበት ጊዜ :attribute መስኩ መገኘት አለበት።',
    'present_with_all' => ':values በሚገኙበት ጊዜ :attribute መስኩ መገኘት አለበት።',
    'prohibited' => ':attribute መስኩ የተከለከለ ነው።',
    'prohibited_if' => ':attribute መስኩ :other :value ሲሆን የተከለከለ ነው።',
    'prohibited_if_accepted' => ':attribute መስኩ :other ሲቀበል የተከለከለ ነው።',
    'prohibited_if_declined' => ':attribute መስኩ :other ሲክድ የተከለከለ ነው።',
    'prohibited_unless' => ':attribute መስኩ :other በ:values ውስጥ ካልሆነ በቀር የተከለከለ ነው።',
    'prohibits' => ':attribute መስኩ :other እንዲገኝ አይፈቅድም።',
    'regex' => ':attribute መስኩ ቅርጸት ልክ አይደለም።',
    'required' => ':attribute መስኩ አስፈላጊ ነው።',
    'required_array_keys' => ':attribute መስኩ ለሚከተሉት ግቤቶች መረጃ መያዝ አለበት። :values',
    'required_if' => ':attribute መስኩ :other :value ሲሆን አስፈላጊ ነው።',
    'required_if_accepted' => ':attribute መስኩ :other ሲቀበል አስፈላጊ ነው።',
    'required_if_declined' => ':attribute መስኩ :other ሲክድ አስፈላጊ ነው።',
    'required_unless' => ':attribute መስኩ :other በ:values ውስጥ ካልሆነ በቀር አስፈላጊ ነው።',
    'required_with' => ':values በሚገኝበት ጊዜ :attribute መስኩ አስፈላጊ ነው።',
    'required_with_all' => ':values በሚገኙበት ጊዜ :attribute መስኩ አስፈላጊ ነው።',
    'required_without' => ':values በማይገኙበት ጊዜ :attribute መስኩ አስፈላጊ ነው።',
    'required_without_all' => ':values ከሆኑት ማንም በማይገኝበት ጊዜ :attribute መስኩ አስፈላጊ ነው።',
    'same' => ':attribute መስኩ ከ:other ጋር መዛመድ አለበት።',
    'size' => [
        'array' => ':attribute መስኩ :size ንጥሎች መያዝ አለበት።',
        'file' => ':attribute መስኩ :size ኪሎባይት መሆን አለበት።',
        'numeric' => ':attribute መስኩ :size መሆን አለበት።',
        'string' => ':attribute መስኩ :size ፊደላት መሆን አለበት።',
    ],
    'starts_with' => ':attribute መስኩ ከሚከተሉት ውስጥ በአንዱ መጀመር አለበት። :values',
    'string' => ':attribute መስኩ ጽሑፍ መሆን አለበት።',
    'timezone' => ':attribute መስኩ ትክክለኛ የሰዓት ዞን መሆን አለበት።',
    'unique' => ':attribute ቀድሞውኑ ወስዷል።',
    'uploaded' => ':attribute መስቀል አልተሳካም።',
    'uppercase' => ':attribute መስኩ በትላልቅ ፊደላት መሆን አለበት።',
    'url' => ':attribute መስኩ ትክክለኛ URL መሆን አለበት።',
    'ulid' => ':attribute መስኩ ትክክለኛ ULID መሆን አለበት።',
    'uuid' => ':attribute መስኩ ትክክለኛ UUID መሆን አለበት።',

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Language Lines
    |--------------------------------------------------------------------------
    |
    | Here you may specify custom validation messages for attributes using the
    | convention "attribute.rule" to name the lines. This makes it quick to
    | specify a specific custom language line for a given attribute rule.
    |
    */

    'custom' => [
        'attribute-name' => [
            'rule-name' => 'ብጁ መልእክት',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Validation Attributes
    |--------------------------------------------------------------------------
    |
    | The following language lines are used to swap our attribute placeholder
    | with something more reader friendly such as "E-Mail Address" instead
    | of "email". This simply helps us make our message more expressive.
    |
    */

    'attributes' => [],

];
