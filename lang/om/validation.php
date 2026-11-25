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

    'accepted' => ':attribute dirree fudhachuu qaba.',
    'accepted_if' => ":attribute dirree yeroo :other :value ta'etti fudhachuu qaba.",
    'active_url' => ":attribute dirree URL sirrii ta'uu qaba.",
    'after' => ":attribute dirree guyyaa :date booda ta'uu qaba.",
    'after_or_equal' => ":attribute dirree guyyaa :date booda ykn walqixa ta'uu qaba.",
    'alpha' => ':attribute dirree qubee qofa qabaachuu qaba.',
    'alpha_dash' => ':attribute dirree qubee, lakkoofsa, hirtaa fi sarboo gadii qofa qabaachuu qaba.',
    'alpha_num' => ':attribute dirree qubee fi lakkoofsa qofa qabaachuu qaba.',
    'any_of' => ':attribute dirree sirrii miti.',
    'array' => ":attribute dirree tarree ta'uu qaba.",
    'array_keys' => ':attribute dirree qabiyyeewwan armaan gadii qofa qabaachuu qaba: :values',
    'ascii' => ':attribute dirree qubee-lakkoofsa fi mallattoolee saanduqa tokkoo qofa qabaachuu qaba.',
    'base64' => ":attribute dirree sarboo Base64 sirrii ta'uu qaba.",
    'before' => ":attribute dirree guyyaa :date dura ta'uu qaba.",
    'before_or_equal' => ":attribute dirree guyyaa :date dura ykn walqixa ta'uu qaba.",
    'between' => [
        'array' => ':attribute dirree meeshaa :min fi :max gidduu qabaachuu qaba.',
        'file' => ":attribute dirree :min hanga :max kiloobayitii ta'uu qaba.",
        'numeric' => ":attribute dirree :min fi :max gidduu ta'uu qaba.",
        'string' => ':attribute dirree :min hanga :max qubee qabaachuu qaba.',
    ],
    'boolean' => ":attribute dirree dhugaa ykn soba ta'uu qaba.",
    'can' => ':attribute dirree dandeettiin hin eeyyamamne qaba.',
    'confirmed' => ':attribute mirkaneeffannaa isaa walitti hin qabu.',
    'contains' => ':attribute dirree wanta barbaachisaa hirte.',
    'current_password' => 'Jechi darbii dhiyeessette sirrii miti.',
    'date' => ":attribute dirree guyyaa sirrii ta'uu qaba.",
    'date_equals' => ":attribute dirree guyyaa :date waliin walqixa ta'uu qaba.",
    'date_format' => ':attribute dirree unka :format hordofuu qaba.',
    'decimal' => ':attribute dirree iddoo desimaalii :decimal qabaachuu qaba.',
    'declined' => ':attribute dirree dhiibuu qaba.',
    'declined_if' => ":attribute dirree yeroo :other :value ta'etti dhiibuu qaba.",
    'different' => ":attribute fi :other adda ta'uu qaba.",
    'digits' => ':attribute dirree :digits lakkoofsa qabaachuu qaba.',
    'digits_between' => ':attribute dirree :min fi :max lakkoofsa gidduu qabaachuu qaba.',
    'dimensions' => ':attribute dirree safuraa pikselii sirrii miti.',
    'distinct' => ":attribute dirree dubbisa irra deebi'ame qaba.",
    'doesnt_contain' => ':attribute dirree keessaa isaaniin tokkoo hin qabaattu: :values',
    'doesnt_end_with' => ":attribute dirree keessaa isaaniin tokkoo dhumatti hin ta'u: :values",
    'doesnt_start_with' => ":attribute dirree keessaa isaaniin tokkoo jalqabuu hin danda'u: :values",
    'email' => ":attribute dirree teessoo imeelii sirrii ta'uu qaba.",
    'encoding' => ":attribute dirree :encoding keessatti kooduu ta'uu qaba.",
    'ends_with' => ":attribute dirree keessaa isaaniin tokkoo dhumatti ta'uu qaba: :values",
    'enum' => ':attribute filame sirrii miti.',
    'exists' => ':attribute filame sirrii miti.',
    'extensions' => ':attribute dirree dabalataalee armaan gadii keessaa tokko qabaachuu qaba: :values',
    'file' => ":attribute dirree faayilii ta'uu qaba.",
    'filled' => ':attribute dirree qabiyye qabaachuu qaba.',
    'gt' => [
        'array' => ':attribute dirree :value meeshaa caalaa qabaachuu qaba.',
        'file' => ":attribute dirree :value kiloobayitii caalaa ta'uu qaba.",
        'numeric' => ":attribute dirree :value caalaa ta'uu qaba.",
        'string' => ':attribute dirree :value qubee caalaa qabaachuu qaba.',
    ],
    'gte' => [
        'array' => ':attribute dirree :value meeshaa ykn caalaa qabaachuu qaba.',
        'file' => ":attribute dirree :value kiloobayitii ykn caalaa ta'uu qaba.",
        'numeric' => ":attribute dirree :value ykn caalaa ta'uu qaba.",
        'string' => ':attribute dirree :value ykn caalaa qubee qabaachuu qaba.',
    ],
    'hex_color' => ":attribute dirree halliyaa heksaadesimaalii sirrii ta'uu qaba.",
    'image' => ":attribute dirree fakaa'uu ta'uu qaba.",
    'in' => ':attribute filame sirrii miti.',
    'in_array' => ':attribute dirree :other keessa jiraachuu qaba.',
    'in_array_keys' => ':attribute dirree qabiyyeewwan armaan gadii keessaa yoo xiqqaate tokko qabaachuu qaba: :values',
    'integer' => ":attribute dirree lakkoofsa guutuu ta'uu qaba.",
    'ip' => ":attribute dirree teessoo IP sirrii ta'uu qaba.",
    'ipv4' => ":attribute dirree teessoo IPv4 sirrii ta'uu qaba.",
    'ipv6' => ":attribute dirree teessoo IPv6 sirrii ta'uu qaba.",
    'json' => ":attribute dirree sarboo JSON sirrii ta'uu qaba.",
    'list' => ":attribute dirree tarree ta'uu qaba.",
    'lowercase' => ":attribute dirree qubee gadii ta'uu qaba.",
    'lt' => [
        'array' => ':attribute dirree :value meeshaa gadii qabaachuu qaba.',
        'file' => ":attribute dirree :value kiloobayitii gadii ta'uu qaba.",
        'numeric' => ":attribute dirree :value gadii ta'uu qaba.",
        'string' => ':attribute dirree :value qubee gadii qabaachuu qaba.',
    ],
    'lte' => [
        'array' => ':attribute dirree :value meeshaa caalaa hin qabaattu.',
        'file' => ":attribute dirree :value kiloobayitii ykn gadii ta'uu qaba.",
        'numeric' => ":attribute dirree :value ykn gadii ta'uu qaba.",
        'string' => ':attribute dirree :value ykn gadii qubee qabaachuu qaba.',
    ],
    'mac_address' => ":attribute dirree teessoo MAC sirrii ta'uu qaba.",
    'max' => [
        'array' => ':attribute dirree :max meeshaa caalaa hin qabaattu.',
        'file' => ":attribute dirree :max kiloobayitii caalaa hin ta'u.",
        'numeric' => ":attribute dirree :max caalaa hin ta'u.",
        'string' => ':attribute dirree :max qubee caalaa hin qabaattu.',
    ],
    'max_digits' => ':attribute dirree :max lakkoofsa caalaa hin qabaattu.',
    'mimes' => ":attribute dirree faayilii unkaalee armaan gadii keessaa tokko ta'uu qaba: :values",
    'mimetypes' => ":attribute dirree faayilii unkaalee armaan gadii keessaa tokko ta'uu qaba: :values",
    'min' => [
        'array' => ':attribute dirree yoo xiqqaate :min meeshaa qabaachuu qaba.',
        'file' => ":attribute dirree yoo xiqqaate :min kiloobayitii ta'uu qaba.",
        'numeric' => ":attribute dirree yoo xiqqaate :min ta'uu qaba.",
        'string' => ':attribute dirree yoo xiqqaate :min qubee qabaachuu qaba.',
    ],
    'min_digits' => ':attribute dirree yoo xiqqaate :min lakkoofsa qabaachuu qaba.',
    'missing' => ':attribute dirree hin jiraachuu qaba.',
    'missing_if' => ":attribute dirree yeroo :other :value ta'etti hin jiraachuu qaba.",
    'missing_unless' => ":attribute dirree :other :value ta'uu baate hin jiraachuu qaba.",
    'missing_with' => ":values jira yoo ta'e, :attribute dirree hin jiraachuu qaba.",
    'missing_with_all' => ":values jiran yoo ta'e, :attribute dirree hin jiraachuu qaba.",
    'multiple_of' => ":attribute dirree :value dachaa ta'uu qaba.",
    'not_in' => ':attribute filame sirrii miti.',
    'not_regex' => ':attribute dirree unka isaa sirrii miti.',
    'numeric' => ":attribute dirree lakkoofsa ta'uu qaba.",
    'password' => [
        'letters' => ':attribute dirree yoo xiqqaate qubee tokko qabaachuu qaba.',
        'mixed' => ':attribute dirree yoo xiqqaate qubee guddaa tokko fi qubee gadii tokko qabaachuu qaba.',
        'numbers' => ':attribute dirree yoo xiqqaate lakkoofsa tokko qabaachuu qaba.',
        'symbols' => ':attribute dirree yoo xiqqaate mallattoo tokko qabaachuu qaba.',
        'uncompromised' => ":attribute dhiyeessette madda dubbii keessa mul'ate. Maaloo :attribute biroo fili.",
    ],
    'present' => ':attribute dirree jiraachuu qaba.',
    'present_if' => ":attribute dirree yeroo :other :value ta'etti jiraachuu qaba.",
    'present_unless' => ":attribute dirree :other :value ta'uu baate jiraachuu qaba.",
    'present_with' => ":values jira yoo ta'e, :attribute dirree jiraachuu qaba.",
    'present_with_all' => ":values jiran yoo ta'e, :attribute dirree jiraachuu qaba.",
    'prohibited' => ':attribute dirree hin eeyyamamu.',
    'prohibited_if' => ":attribute dirree yeroo :other :value ta'etti hin eeyyamamu.",
    'prohibited_if_accepted' => ":attribute dirree :other fudhate yoo ta'e hin eeyyamamu.",
    'prohibited_if_declined' => ":attribute dirree :other dhiibe yoo ta'e hin eeyyamamu.",
    'prohibited_unless' => ':attribute dirree :other :values keessa jiraachuu baate hin eeyyamamu.',
    'prohibits' => ':attribute dirree :other jiraachuu hin eeyyamu.',
    'regex' => ':attribute dirree unka isaa sirrii miti.',
    'required' => ':attribute dirree barbaachisa dha.',
    'required_array_keys' => ':attribute dirree seekkoo armaan gadiif qabiyye qabaachuu qaba: :values',
    'required_if' => ":attribute dirree yeroo :other :value ta'etti barbaachisa dha.",
    'required_if_accepted' => ":attribute dirree :other fudhate yoo ta'e barbaachisa dha.",
    'required_if_declined' => ":attribute dirree :other dhiibe yoo ta'e barbaachisa dha.",
    'required_unless' => ':attribute dirree :other :values keessa jiraachuu baate barbaachisa dha.',
    'required_with' => ":values jira yoo ta'e, :attribute dirree barbaachisa dha.",
    'required_with_all' => ":values jiran yoo ta'e, :attribute dirree barbaachisa dha.",
    'required_without' => ":values hin jiran yoo ta'e, :attribute dirree barbaachisa dha.",
    'required_without_all' => ":values hundi hin jiran yoo ta'e, :attribute dirree barbaachisa dha.",
    'same' => ":attribute dirree :other waliin walqixa ta'uu qaba.",
    'size' => [
        'array' => ':attribute dirree :size meeshaa qabaachuu qaba.',
        'file' => ":attribute dirree :size kiloobayitii ta'uu qaba.",
        'numeric' => ":attribute dirree :size ta'uu qaba.",
        'string' => ':attribute dirree :size qubee qabaachuu qaba.',
    ],
    'starts_with' => ':attribute dirree keessaa isaaniin tokkoo jalqabuu qaba: :values',
    'string' => ":attribute dirree sarboo ta'uu qaba.",
    'timezone' => ":attribute dirree naannoo yeroo sirrii ta'uu qaba.",
    'unique' => ':attribute hamma duraa fudhatameera.',
    'uploaded' => ":attribute ka'uu hin dandeenye.",
    'uppercase' => ":attribute dirree qubee guddaa ta'uu qaba.",
    'url' => ":attribute dirree URL sirrii ta'uu qaba.",
    'ulid' => ":attribute dirree ULID sirrii ta'uu qaba.",
    'uuid' => ":attribute dirree UUID sirrii ta'uu qaba.",

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
            'rule-name' => 'dubbii addaa',
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
