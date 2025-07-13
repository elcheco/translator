# CLDR Format Examples for NEON Translation Files

## English (en_US.neon)

### Legacy format (still supported)
```neon
Welcome: Welcome to our website
Hello %s: Hello %s
```

### Simple CLDR format
```neon
items_count:
    one: You have one item
    other: You have {count} items
```

### CLDR with zero
```neon
messages_count:
    zero: You have no messages
    one: You have one message
    other: You have {count} messages
```
### Mixed parameters
```neon
user_messages:
    one: "{user} has one message"
    other: "{user} has {count} messages"
```

## Czech (cs_CZ.neon)

### Legacy format with ranges (still supported)
```neon
%s bedrooms:
    0: žádná ložnice
    "1-4": %s ložnice
    5: %s ložnic
```
### CLDR format - Czech uses one, few, many (for decimals), other
```neon
bedrooms_count:
    one: jedna ložnice
    few: "{count} ložnice"
    many: "{count} ložnice"      # for decimal numbers like 1.5, 2.5
    other: "{count} ložnic"

messages_count:
    one: Máte jednu zprávu
    few: "Máte {count} zprávy"
    many: "Máte {count} zprávy"   # for decimals
    other: "Máte {count} zpráv"

days_remaining:
    one: Zbývá jeden den
    few: "Zbývají {count} dny"
    many: "Zbývá {count} dne"     # for 1.5, 2.5 days etc.
    other: "Zbývá {count} dní"
```

### Examples with decimals
```neon
apples:
    one: "{count} jablko"
    few: "{count} jablka"
    many: "{count} jablka"        # 1,5 jablka, 2,5 jablka
    other: "{count} jablek"

kilometers:
    one: "{count} kilometr"
    few: "{count} kilometry"
    many: "{count} kilometru"     # 1,5 kilometru, 3,7 kilometru
    other: "{count} kilometrů"
```

## Russian (ru_RU.neon)

# CLDR format with proper Russian plurals
```neon
messages_count:
    zero: У вас нет сообщений
    one: "У вас {count} сообщение"
    few: "У вас {count} сообщения"
    many: "У вас {count} сообщений"

days_count:
    one: "{count} день"
    few: "{count} дня"
    many: "{count} дней"

# With additional parameters
user_posts:
    zero: "{user} еще не написал постов"
    one: "{user} написал {count} пост"
    few: "{user} написал {count} поста"
    many: "{user} написал {count} постов"
```

## Polish (pl_PL.neon)

# Polish has complex plural rules
```neon
files_count:
    one: "{count} plik"
    few: "{count} pliki"
    many: "{count} plików"

people_count:
    one: "{count} osoba"
    few: "{count} osoby"
    many: "{count} osób"
```

## Slovenian (sl_SI.neon)

### Slovenian has dual form
```neon
votes_count:
    one: "{count} glas"
    two: "{count} glasova"
    few: "{count} glasovi"
    other: "{count} glasov"
```
## Arabic (ar_SA.neon)

### Arabic has all 6 plural forms
```neon
items_count:
    zero: "لا توجد عناصر"
    one: "عنصر واحد"
    two: "عنصران"
    few: "{count} عناصر"
    many: "{count} عنصرًا"
    other: "{count} عنصر"
```

## Japanese (ja_JP.neon)

### Languages without plural distinctions use only 'other'
```neon
items_count:
    other: "{count}個のアイテム"

messages_count:
    other: "{count}件のメッセージ"
```

## Advanced Examples

### Ordinal numbers (future enhancement)
```neon
place_in_race:
    one: "{count}st place"
    two: "{count}nd place"
    few: "{count}rd place"
    other: "{count}th place"
```
### Gender support (future enhancement)
```neon
user_action:
    male: "{user} updated his profile"
    female: "{user} updated her profile"
    other: "{user} updated their profile"
```
### Nested plurals (complex ICU pattern)
```neon
cart_summary:
    pattern: "You have {item_count, plural, =0 {no items} one {one item} other {# items}} in {cart_count, plural, one {one cart} other {# carts}}"
```
