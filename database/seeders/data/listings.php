<?php

/*
 * Synthetic demonstration listings. Every name, price and schedule here is
 * invented for the demo; listings are flagged is_illustrative = true.
 * Prices are in rupees here and converted to paise by the seeder.
 *
 * Photos come from public/images/listings/credits.json (hand-picked, freely licensed).
 */

$week = [0, 1, 2, 3, 4, 5, 6];
$dinner = fn (int $seats, array $days = [0, 1, 2, 3, 4, 5, 6]) => array_map(
    fn ($d) => ['weekday' => $d, 'start' => '19:00', 'end' => '22:30', 'every' => 30, 'length' => 90, 'capacity' => $seats], $days);
$lunchDinner = fn (int $seats, array $days) => array_merge(
    array_map(fn ($d) => ['weekday' => $d, 'start' => '12:00', 'end' => '15:00', 'every' => 30, 'length' => 75, 'capacity' => $seats], $days),
    array_map(fn ($d) => ['weekday' => $d, 'start' => '19:00', 'end' => '22:30', 'every' => 30, 'length' => 90, 'capacity' => $seats], $days));
$sessions = fn (array $starts, int $minutes, int $capacity, array $days) => array_merge(...array_map(
    fn ($t) => array_map(fn ($d) => ['weekday' => $d, 'start' => $t, 'end' => date('H:i', strtotime($t) + $minutes * 60), 'every' => $minutes, 'length' => $minutes, 'capacity' => $capacity], $days), $starts));

return [
    // ---------------------------------------------------------------- STAYS
    ['type' => 'stay', 'dest' => 'swiss-alps', 'cat' => 'mountain-lodge', 'provider' => 0, 'featured' => true,
        'title' => 'Matterhorn View Chalet', 'price' => 38500, 'policy' => 'moderate', 'mode' => 'request',
        'summary' => 'Larch-wood chalet with a fireplace lounge and uninterrupted Matterhorn views.',
        'description' => "A family-run chalet ten minutes' walk from the village centre. Every suite faces the Matterhorn, breakfast is served by the fire, and the ski bus stops at the gate.\n\nIllustrative listing created for the Excursio demo.",
        'detail' => ['check_in_time' => '15:00', 'check_out_time' => '10:00', 'min_nights' => 2, 'house_rules' => 'Quiet hours 22:00–07:00. Ski boots stay in the boot room.'],
        'units' => [['Alpine Double', 2, 4, 38500, 'One king bed'], ['Matterhorn Suite', 3, 2, 56000, 'King bed + sofa bed'], ['Family Loft', 5, 1, 72000, 'King bed + two singles']],
        'amenities' => ['wifi', 'fireplace', 'breakfast', 'ski-storage', 'mountain-view', 'heating'], ],

    ['type' => 'stay', 'dest' => 'kyoto', 'cat' => 'ryokan', 'provider' => 1, 'featured' => true,
        'title' => 'Higashiyama Garden Ryokan', 'price' => 24000, 'policy' => 'strict', 'mode' => 'request',
        'summary' => 'Tatami rooms around a moss garden, a short walk from Kiyomizu-dera.',
        'description' => "A traditional inn with eight tatami rooms opening onto a private moss garden. Cypress baths, yukata and a seasonal kaiseki breakfast are included.\n\nIllustrative listing created for the Excursio demo.",
        'detail' => ['check_in_time' => '15:00', 'check_out_time' => '10:00', 'min_nights' => 1, 'house_rules' => 'Shoes off indoors. Bath hours 16:00–23:00.'],
        'units' => [['Garden Tatami Room', 2, 5, 24000, 'Two futons'], ['Hinoki Bath Suite', 3, 2, 39000, 'Three futons, private cypress bath']],
        'amenities' => ['wifi', 'breakfast', 'garden', 'private-bath', 'air-conditioning'], ],

    ['type' => 'stay', 'dest' => 'bali', 'cat' => 'villa', 'provider' => 2, 'featured' => true,
        'title' => 'Ubud Jungle Pool Villa', 'price' => 18500, 'policy' => 'flexible', 'mode' => 'instant',
        'summary' => 'Private pool villas above the Ayung river valley, wrapped in jungle.',
        'description' => "Six thatched villas, each with its own infinity pool over the river gorge. Morning yoga, an open-air bathtub and bicycles for the rice terraces.\n\nIllustrative listing created for the Excursio demo.",
        'detail' => ['check_in_time' => '14:00', 'check_out_time' => '12:00', 'min_nights' => 2],
        'units' => [['One-Bedroom Pool Villa', 2, 4, 18500, 'King bed'], ['Two-Bedroom Pool Villa', 4, 2, 29500, 'King + twin']],
        'amenities' => ['wifi', 'pool', 'breakfast', 'air-conditioning', 'spa', 'garden'], ],

    ['type' => 'stay', 'dest' => 'tuscany', 'cat' => 'farmhouse', 'provider' => 0, 'featured' => false,
        'title' => 'Podere delle Colline', 'price' => 21000, 'policy' => 'moderate', 'mode' => 'request',
        'summary' => 'A restored stone farmhouse among Chianti vineyards, with a pool and olive grove.',
        'description' => "Thick stone walls, terracotta floors and a long table under the pergola. The owners run cooking evenings twice a week.\n\nIllustrative listing created for the Excursio demo.",
        'detail' => ['check_in_time' => '16:00', 'check_out_time' => '10:00', 'min_nights' => 3],
        'units' => [['Vineyard Room', 2, 4, 21000, 'Queen bed'], ['Olive Grove Apartment', 4, 2, 34000, 'Queen + two singles, kitchen']],
        'amenities' => ['wifi', 'pool', 'breakfast', 'garden', 'parking', 'kitchen'], ],

    ['type' => 'stay', 'dest' => 'iceland', 'cat' => 'cabin', 'provider' => 2, 'featured' => true,
        'title' => 'Black Sand Glass Cabins', 'price' => 26500, 'policy' => 'moderate', 'mode' => 'instant',
        'summary' => 'Glass-roofed cabins on the south coast for watching the northern lights from bed.',
        'description' => "Minimal timber cabins with a glass ceiling over the bed, a hot tub on the deck and the Reynisfjara cliffs on the horizon.\n\nIllustrative listing created for the Excursio demo.",
        'detail' => ['check_in_time' => '15:00', 'check_out_time' => '11:00', 'min_nights' => 1],
        'units' => [['Aurora Glass Cabin', 2, 6, 26500, 'Queen bed under glass roof']],
        'amenities' => ['wifi', 'hot-tub', 'heating', 'parking', 'kitchen'], ],

    ['type' => 'stay', 'dest' => 'coorg', 'cat' => 'homestay', 'provider' => 1, 'featured' => true,
        'title' => 'Misty Estate Plantation Bungalow', 'price' => 7800, 'policy' => 'flexible', 'mode' => 'request',
        'summary' => 'A 1920s planter\'s bungalow on a working coffee estate.',
        'description' => "Wide verandas, home-cooked Kodava meals and morning walks through coffee and pepper with the estate manager.\n\nIllustrative listing created for the Excursio demo.",
        'detail' => ['check_in_time' => '12:00', 'check_out_time' => '11:00', 'min_nights' => 1],
        'units' => [['Estate Room', 2, 4, 7800, 'Queen bed'], ['Heritage Suite', 3, 1, 11500, 'King bed + day bed']],
        'amenities' => ['wifi', 'breakfast', 'garden', 'parking', 'mountain-view'], ],

    ['type' => 'stay', 'dest' => 'manali', 'cat' => 'cabin', 'provider' => 1, 'featured' => false,
        'title' => 'Beas Riverside Cedar Cabins', 'price' => 6200, 'policy' => 'moderate', 'mode' => 'instant',
        'summary' => 'Deodar-wood cabins on the Beas river, twenty minutes from Solang Valley.',
        'description' => "Wood-fired heating, apple orchards and river sounds. The hosts can arrange ski hire and transfers to Solang.\n\nIllustrative listing created for the Excursio demo.",
        'detail' => ['check_in_time' => '13:00', 'check_out_time' => '11:00', 'min_nights' => 1],
        'units' => [['River Cabin', 3, 5, 6200, 'Queen bed + single'], ['Orchard Family Cabin', 5, 2, 9800, 'Two queens + bunk']],
        'amenities' => ['wifi', 'heating', 'fireplace', 'parking', 'mountain-view'], ],

    ['type' => 'stay', 'dest' => 'goa', 'cat' => 'villa', 'provider' => 2, 'featured' => false,
        'title' => 'Casa Assagao Heritage Villa', 'price' => 14500, 'policy' => 'flexible', 'mode' => 'instant',
        'summary' => 'A restored Indo-Portuguese villa with a courtyard pool in quiet Assagao.',
        'description' => "High ceilings, oyster-shell windows and a courtyard pool. Ten minutes from Anjuna beach and the Saturday night market.\n\nIllustrative listing created for the Excursio demo.",
        'detail' => ['check_in_time' => '14:00', 'check_out_time' => '11:00', 'min_nights' => 2],
        'units' => [['Courtyard Room', 2, 4, 14500, 'King bed'], ['Whole Villa', 8, 1, 52000, 'Four bedrooms']],
        'amenities' => ['wifi', 'pool', 'air-conditioning', 'kitchen', 'parking'], ],

    ['type' => 'stay', 'dest' => 'udaipur', 'cat' => 'boutique-hotel', 'provider' => 0, 'featured' => true,
        'title' => 'Lake Pichola Haveli', 'price' => 16800, 'policy' => 'moderate', 'mode' => 'request',
        'summary' => 'A 200-year-old haveli with jharokha windows facing the City Palace.',
        'description' => "Hand-painted rooms, a rooftop terrace over Lake Pichola and evening boat rides from the private ghat.\n\nIllustrative listing created for the Excursio demo.",
        'detail' => ['check_in_time' => '13:00', 'check_out_time' => '11:00', 'min_nights' => 1],
        'units' => [['Heritage Room', 2, 6, 16800, 'King bed'], ['Lake-View Jharokha Suite', 3, 2, 27500, 'King bed + day bed']],
        'amenities' => ['wifi', 'air-conditioning', 'breakfast', 'rooftop', 'lake-view'], ],

    // ---------------------------------------------------------------- DINING
    ['type' => 'dining', 'dest' => 'kyoto', 'cat' => 'tasting-menu', 'provider' => 1, 'featured' => true,
        'title' => 'Kamo Omakase Counter', 'price' => 14500, 'policy' => 'strict', 'mode' => 'instant',
        'summary' => 'Ten seats at a hinoki counter; a seasonal omakase from the Kamo river market.',
        'description' => "One counter, one chef and around fifteen small courses that change with the market each morning.\n\nIllustrative listing created for the Excursio demo.",
        'detail' => ['cuisine' => 'Japanese', 'price_band' => 4, 'dress_code' => 'Smart casual', 'max_party_size' => 4, 'deposit_per_guest' => 2500,
            'policies' => 'Please arrive on time; courses are served to the whole counter together.'],
        'schedules' => $dinner(10, [2, 3, 4, 5, 6]),
        'amenities' => ['counter-seating', 'vegetarian'], ],

    ['type' => 'dining', 'dest' => 'tuscany', 'cat' => 'fine-dining', 'provider' => 0, 'featured' => true,
        'title' => 'Osteria del Cipresso', 'price' => 6500, 'policy' => 'flexible', 'mode' => 'instant',
        'summary' => 'Hand-made pasta and estate wines on a vine-covered terrace.',
        'description' => "Pici rolled to order, wood-fired bistecca and wine from the hill you're looking at.\n\nIllustrative listing created for the Excursio demo.",
        'detail' => ['cuisine' => 'Italian', 'price_band' => 3, 'max_party_size' => 8, 'deposit_per_guest' => 0],
        'schedules' => $lunchDinner(28, [0, 2, 3, 4, 5, 6]),
        'amenities' => ['outdoor-seating', 'vegetarian', 'wine-list'], ],

    ['type' => 'dining', 'dest' => 'udaipur', 'cat' => 'rooftop', 'provider' => 0, 'featured' => true,
        'title' => 'Jharokha Rooftop', 'price' => 2800, 'policy' => 'flexible', 'mode' => 'instant',
        'summary' => 'Rajasthani thalis and lake-view sunsets on a candlelit rooftop.',
        'description' => "Laal maas, gatte ki sabzi and dal baati churma, served as the palace lights come on across the lake.\n\nIllustrative listing created for the Excursio demo.",
        'detail' => ['cuisine' => 'Rajasthani', 'price_band' => 2, 'max_party_size' => 10, 'deposit_per_guest' => 0],
        'schedules' => $dinner(40),
        'amenities' => ['outdoor-seating', 'vegetarian', 'lake-view'], ],

    ['type' => 'dining', 'dest' => 'swiss-alps', 'cat' => 'fine-dining', 'provider' => 0, 'featured' => false,
        'title' => 'Stübli am Gletscher', 'price' => 9500, 'policy' => 'moderate', 'mode' => 'instant',
        'summary' => 'Fondue, raclette and alpine game in a 300-year-old wooden stübli.',
        'description' => "Low ceilings, candlelight and cheese from the next valley. Book the window table for the Matterhorn at dusk.\n\nIllustrative listing created for the Excursio demo.",
        'detail' => ['cuisine' => 'Swiss', 'price_band' => 3, 'max_party_size' => 8, 'deposit_per_guest' => 0],
        'schedules' => $dinner(30, [0, 1, 3, 4, 5, 6]),
        'amenities' => ['vegetarian', 'wine-list', 'mountain-view'], ],

    ['type' => 'dining', 'dest' => 'goa', 'cat' => 'seafood', 'provider' => 2, 'featured' => true,
        'title' => 'Tide & Toddy', 'price' => 2200, 'policy' => 'flexible', 'mode' => 'instant',
        'summary' => 'Goan seafood curries and grilled catch under fairy lights by the creek.',
        'description' => "Recheado mackerel, prawn balchão and poi bread, with feni cocktails and live acoustic music on Fridays.\n\nIllustrative listing created for the Excursio demo.",
        'detail' => ['cuisine' => 'Goan', 'price_band' => 2, 'max_party_size' => 12, 'deposit_per_guest' => 0],
        'schedules' => $lunchDinner(50, $week),
        'amenities' => ['outdoor-seating', 'vegetarian', 'live-music'], ],

    ['type' => 'dining', 'dest' => 'coorg', 'cat' => 'cafe', 'provider' => 1, 'featured' => false,
        'title' => 'Estate Coffee Table', 'price' => 900, 'policy' => 'flexible', 'mode' => 'instant',
        'summary' => 'Single-estate pour-overs and Kodava brunch on a plantation veranda.',
        'description' => "Akki roti, pandi curry and coffee roasted on site, with a short cupping session on weekends.\n\nIllustrative listing created for the Excursio demo.",
        'detail' => ['cuisine' => 'Kodava · Café', 'price_band' => 1, 'max_party_size' => 8, 'deposit_per_guest' => 0],
        'schedules' => array_map(fn ($d) => ['weekday' => $d, 'start' => '08:00', 'end' => '13:00', 'every' => 30, 'length' => 60, 'capacity' => 24], $week),
        'amenities' => ['outdoor-seating', 'vegetarian'], ],

    // ---------------------------------------------------------------- ACTIVITIES
    ['type' => 'activity', 'dest' => 'swiss-alps', 'cat' => 'skiing', 'provider' => 0, 'featured' => true,
        'title' => 'Matterhorn Ski Day with a Guide', 'price' => 16500, 'policy' => 'moderate', 'mode' => 'instant',
        'summary' => 'A full day on the Matterhorn glacier paradise with a certified ski guide.',
        'description' => "Small groups matched by level, crossing into Cervinia for lunch if conditions allow. Lift pass not included.\n\nIllustrative listing created for the Excursio demo.",
        'detail' => ['duration_minutes' => 360, 'difficulty' => 'moderate', 'min_age' => 12, 'weather_dependent' => true, 'booking_cutoff_hours' => 18,
            'inclusions' => "Certified guide\nAvalanche transceiver\nLunch-stop recommendation", 'equipment' => 'Bring your own skis or rent in the village.',
            'safety_info' => 'Participants must ski confidently on red runs. The guide may change the route for snow conditions.'],
        'schedules' => $sessions(['09:00'], 360, 6, $week),
        'amenities' => ['guide', 'equipment-included'], ],

    ['type' => 'activity', 'dest' => 'manali', 'cat' => 'skiing', 'provider' => 1, 'featured' => true,
        'title' => 'Beginner Ski Lessons at Solang', 'price' => 3200, 'policy' => 'flexible', 'mode' => 'instant',
        'summary' => 'Two-hour lessons for first-timers on the gentle slopes of Solang Valley.',
        'description' => "Learn to stop, turn and ride the surface lift with a patient instructor. Skis, boots and poles included.\n\nIllustrative listing created for the Excursio demo.",
        'detail' => ['duration_minutes' => 120, 'difficulty' => 'easy', 'min_age' => 8, 'weather_dependent' => true, 'booking_cutoff_hours' => 6,
            'inclusions' => "Instructor\nSkis, boots and poles", 'equipment' => 'Wear waterproof layers and gloves.',
            'safety_info' => 'Helmets are provided and must be worn.'],
        'schedules' => $sessions(['09:30', '12:00', '14:30'], 120, 8, $week),
        'amenities' => ['guide', 'equipment-included', 'beginner-friendly'], ],

    ['type' => 'activity', 'dest' => 'manali', 'cat' => 'trekking', 'provider' => 1, 'featured' => false,
        'title' => 'Jogini Falls Guided Trek', 'price' => 1500, 'policy' => 'flexible', 'mode' => 'instant',
        'summary' => 'A half-day trail through Vashisht village to the Jogini waterfall.',
        'description' => "Stone paths, apple orchards and a 150-foot waterfall, with chai at a village tea stall on the way back.\n\nIllustrative listing created for the Excursio demo.",
        'detail' => ['duration_minutes' => 240, 'difficulty' => 'easy', 'min_age' => 7, 'weather_dependent' => true, 'booking_cutoff_hours' => 12,
            'inclusions' => "Local guide\nChai and snacks", 'safety_info' => 'Wear trekking shoes; the path is slippery after rain.'],
        'schedules' => $sessions(['08:00'], 240, 12, $week),
        'amenities' => ['guide', 'beginner-friendly'], ],

    ['type' => 'activity', 'dest' => 'bali', 'cat' => 'scuba-diving', 'provider' => 2, 'featured' => true,
        'title' => 'Tulamben Wreck Dive', 'price' => 9800, 'policy' => 'moderate', 'mode' => 'instant',
        'summary' => 'Two shore dives on the USAT Liberty wreck for certified divers.',
        'description' => "One of the most accessible wrecks in the world, covered in coral and visited by bumphead parrotfish at dawn.\n\nIllustrative listing created for the Excursio demo.",
        'detail' => ['duration_minutes' => 300, 'difficulty' => 'moderate', 'min_age' => 12, 'weather_dependent' => true, 'booking_cutoff_hours' => 24,
            'inclusions' => "Two dives\nFull equipment\nLunch", 'equipment' => 'Bring your certification card.',
            'safety_info' => 'Open Water certification required. No flying within 24 hours after diving.'],
        'schedules' => $sessions(['07:30'], 300, 6, [1, 2, 3, 4, 5, 6]),
        'amenities' => ['guide', 'equipment-included', 'meal-included'], ],

    ['type' => 'activity', 'dest' => 'goa', 'cat' => 'kayaking', 'provider' => 2, 'featured' => true,
        'title' => 'Sunset Mangrove Kayaking', 'price' => 1800, 'policy' => 'flexible', 'mode' => 'instant',
        'summary' => 'Paddle the quiet backwaters of the Mandovi at golden hour.',
        'description' => "Calm water, kingfishers and mangrove tunnels, ending with the sunset on the river mouth.\n\nIllustrative listing created for the Excursio demo.",
        'detail' => ['duration_minutes' => 150, 'difficulty' => 'easy', 'min_age' => 10, 'weather_dependent' => true, 'booking_cutoff_hours' => 4,
            'inclusions' => "Kayak and paddle\nLife jacket\nGuide", 'safety_info' => 'Life jackets must be worn at all times.'],
        'schedules' => $sessions(['16:00'], 150, 10, $week),
        'amenities' => ['guide', 'equipment-included', 'beginner-friendly'], ],

    ['type' => 'activity', 'dest' => 'iceland', 'cat' => 'trekking', 'provider' => 2, 'featured' => true,
        'title' => 'Sólheimajökull Glacier Walk', 'price' => 11500, 'policy' => 'moderate', 'mode' => 'instant',
        'summary' => 'Crampons on: three hours on a glacier tongue with a mountain guide.',
        'description' => "Ice ridges, sinkholes and black ash layers from old eruptions, explained by a certified glacier guide.\n\nIllustrative listing created for the Excursio demo.",
        'detail' => ['duration_minutes' => 180, 'difficulty' => 'moderate', 'min_age' => 10, 'weather_dependent' => true, 'booking_cutoff_hours' => 24,
            'inclusions' => "Glacier guide\nCrampons, harness, helmet, ice axe", 'safety_info' => 'Waterproof hiking boots required.'],
        'schedules' => $sessions(['09:00', '13:00'], 180, 12, $week),
        'amenities' => ['guide', 'equipment-included'], ],

    ['type' => 'activity', 'dest' => 'kyoto', 'cat' => 'workshop', 'provider' => 1, 'featured' => true,
        'title' => 'Tea Ceremony in a Machiya', 'price' => 4800, 'policy' => 'moderate', 'mode' => 'instant',
        'summary' => 'Whisk your own matcha with a tea master in a 100-year-old townhouse.',
        'description' => "A quiet hour learning the movements of chado, with seasonal wagashi sweets and a short garden walk.\n\nIllustrative listing created for the Excursio demo.",
        'detail' => ['duration_minutes' => 75, 'difficulty' => null, 'min_age' => 6, 'weather_dependent' => false, 'booking_cutoff_hours' => 12,
            'inclusions' => "Matcha and wagashi\nEnglish-speaking host"],
        'schedules' => $sessions(['10:00', '13:30', '15:30'], 75, 8, [0, 1, 3, 4, 5, 6]),
        'amenities' => ['guide', 'beginner-friendly'], ],

    ['type' => 'activity', 'dest' => 'tuscany', 'cat' => 'workshop', 'provider' => 0, 'featured' => false,
        'title' => 'Pasta-Making in the Vineyard Kitchen', 'price' => 7200, 'policy' => 'flexible', 'mode' => 'instant',
        'summary' => 'Make tagliatelle and pici from scratch, then eat them with estate wine.',
        'description' => "A hands-on class with a local nonna, finishing with lunch on the terrace.\n\nIllustrative listing created for the Excursio demo.",
        'detail' => ['duration_minutes' => 180, 'difficulty' => null, 'min_age' => 8, 'weather_dependent' => false, 'booking_cutoff_hours' => 24,
            'inclusions' => "Ingredients and apron\nLunch with wine (soft drinks for under-18s)"],
        'schedules' => $sessions(['10:00'], 180, 10, [1, 2, 3, 4, 5]),
        'amenities' => ['meal-included', 'beginner-friendly'], ],

    ['type' => 'activity', 'dest' => 'udaipur', 'cat' => 'city-tour', 'provider' => 0, 'featured' => false,
        'title' => 'Old City Heritage Walk', 'price' => 1200, 'policy' => 'flexible', 'mode' => 'instant',
        'summary' => 'Morning walk through bazaars, havelis and ghats with a local historian.',
        'description' => "Jagdish Temple, the miniature-painting lanes and Gangaur Ghat before the crowds arrive.\n\nIllustrative listing created for the Excursio demo.",
        'detail' => ['duration_minutes' => 150, 'difficulty' => 'easy', 'min_age' => null, 'weather_dependent' => false, 'booking_cutoff_hours' => 6,
            'inclusions' => "Historian guide\nMasala chai"],
        'schedules' => $sessions(['07:30'], 150, 15, $week),
        'amenities' => ['guide', 'beginner-friendly'], ],

    ['type' => 'activity', 'dest' => 'coorg', 'cat' => 'camping', 'provider' => 1, 'featured' => false,
        'title' => 'Riverside Camping Night', 'price' => 3500, 'policy' => 'moderate', 'mode' => 'instant',
        'summary' => 'Tents by the Kaveri, a bonfire dinner and a dawn bird walk.',
        'description' => "Pre-pitched tents, a barbecue under the stars and coffee at sunrise with a naturalist.\n\nIllustrative listing created for the Excursio demo.",
        'detail' => ['duration_minutes' => 960, 'difficulty' => 'easy', 'min_age' => 6, 'weather_dependent' => true, 'booking_cutoff_hours' => 24,
            'inclusions' => "Tent and sleeping bag\nDinner and breakfast\nNaturalist guide", 'safety_info' => 'No swimming in the river after dark.'],
        'schedules' => $sessions(['16:00'], 960, 20, [5, 6]),
        'amenities' => ['guide', 'meal-included', 'equipment-included'], ],
];
