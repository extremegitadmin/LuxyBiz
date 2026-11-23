/* =========================================================
   LuxyCatalog: the six packages shown on the home page.
   Tour Details, My Bookings and the dashboard all read from here.
   In production this is the "data from application" the wireframe asks for:
   replace the object below with a fetch to your packages API.
   Prices are stored per package in both currencies (set by hand, no live conversion).
   All copy is demo content.
   ========================================================= */
(function (w) {
  'use strict';
  var U = function (id, wd, ht) { return 'https://images.unsplash.com/photo-' + id + '?fm=jpg&q=80&w=' + wd + '&h=' + ht + '&fit=crop&auto=format'; };
  var ID = { munnar: '1764012393710-be2d33971ac0', picker: '1533268722164-c7323ba5690c', gardens: '1736950825214-1b5e2bdbb5f8', leaf: '1649349103703-f9b2e71fbd0e', boat: '1654530050931-3b02b28570c1', lighthouse: '1695030744519-3f3042b3af26', theyyam: '1741243780976-998c58d1b3aa' };
  var L = function (k, wd, ht) { return U(ID[k], wd || 1920, ht || 1080); };
  var G = function (src, alt, cap) { return { src: src, alt: alt, cap: cap }; };

  var BASE_INC = ['Private vehicle and driver for the whole journey', 'Airport transfers', 'Daily breakfast', 'One named planner, on call from booking to departure'];
  var BASE_EXC = ['Flights to and from Kerala', 'Travel insurance', 'Meals and drinks not listed in the itinerary', 'Personal expenses and tips', 'Optional activities booked on the day', 'Any visa or entry fees'];
  var PRO = { name: '[Name]', designation: '[Designation]', creds: '[Approved credentials]', bio: '[Short biography]', link: '' };   /* supplied by the client; link only if confirmed */

  var PACK = {
    Highlands: 'Light layers, a rain jacket and comfortable walking shoes. Evenings in the hills are cool all year.',
    Backwaters: 'Light cotton clothes, a sun hat, sunscreen and insect repellent. Soft-soled shoes are best on a houseboat.',
    Coast: 'Swimwear, sunscreen, a hat and sandals. Bring one light layer for breezy evenings.',
    Wellness: 'Loose, comfortable clothing for morning practice, swimwear and a light wrap for the evenings.',
    Culture: 'Modest clothing for temples (shoulders and knees covered) and shoes that are easy to slip on and off.'
  };
  function knowFor(t) {
    return [['Guides and hosts', 'Your guides and hosts live and work in the region. Your planner introduces everyone before you travel.'],
            ['Comfort and pace', 'Days are short and unhurried. Tell your planner about any mobility needs and we will adjust the plan.'],
            ['What to pack', PACK[t.region] || PACK.Highlands]];
  }
  var T = {};
  function add(t) { t.pro = t.pro || PRO; t.exclude = t.exclude || BASE_EXC; t.know = t.know || knowFor(t); T[t.slug] = t; }

  add({
    slug: 'munnar-mist-tea-trails', name: 'Munnar Mist & Tea Trails', dest: 'Munnar, Kerala', region: 'Highlands', cats: 'highlands', nights: 5, usd: 1420, inr: 125000, senior: true,
    short: "Estate walks, a planter's bungalow and slow mornings above the clouds.",
    card: U(ID.munnar, 800, 900), hero: L('munnar'), heroAlt: 'Misty tea hills of Munnar at first light',
    lead: 'Cool air, slow mornings and a landscape that rewards unhurried travel.',
    overview: ["Over five nights you stay in a restored planter's bungalow and a quiet hill resort, walk through working tea estates with a local guide, and eat food cooked by the families who grow it.", 'There is no early alarm and no checklist of viewpoints. Each day has one anchor, a walk, a visit or a meal, and the rest is left open so the hills have time to work on you.'],
    glance: [['Starts and ends', 'Kochi'], ['Best months', 'September to March'], ['Pace', 'Slow, mostly on foot'], ['Where you stay', 'Estate bungalow, hill resort'], ['Travelling as', 'Private, for your party only']],
    gallery: [G(L('picker', 1000, 800), 'A tea picker harvesting on a Munnar estate', 'Picking the first flush'), G(L('gardens', 800, 600), 'Tea gardens of the Kannan Devan hills', 'Terraces above the valley'), G(L('leaf', 800, 600), 'A Kerala breakfast served on a banana leaf', 'Breakfast on banana leaf')],
    include: ['Five nights in an estate bungalow and a hill resort, with daily breakfast', 'Guided estate walks and a tea factory visit', 'A cooking session with a local family'].concat(BASE_INC.slice(0, 2), [BASE_INC[3]]),
    know: [['Guides and hosts', 'Estate walks are led by local guides who live and work in the tea country. Your planner introduces everyone before you travel.'], ['Comfort and pace', 'Days are short and unhurried. Paths are gentle, with some steps and slopes. Tell your planner about any mobility needs and we will adjust.'], ['What to pack', 'Light layers, a rain jacket and comfortable walking shoes. Evenings in the hills are cool all year.']],
    days: [
      { t: 'Arrival in Kochi, then up into the hills', s: 'Kochi to Munnar, about 4 to 5 hours by road', ev: [['10:00', 'Your driver meets you at Kochi airport and helps with bags. The road climbs gently from the plains into the tea country.'], ['13:30', 'Lunch at a family-run kitchen on the way, with a short stop at a waterfall viewpoint if the weather is clear.'], ['16:30', 'Arrive at your estate bungalow. Tea and something sweet on the veranda while the light changes over the valley.'], ['19:30', 'A quiet dinner at the bungalow, then an early night. The hills are cool after dark.']] },
      { t: 'Into the tea estates', s: 'A guided morning walk and a slow afternoon', ev: [['07:00', 'Walk the terraces with an estate guide while the mist is still low.'], ['09:30', 'Breakfast back at the bungalow.'], ['11:00', 'Visit a working tea factory and taste the leaf at each stage.'], ['17:30', 'Sunset from a hill viewpoint, with a flask of fresh tea.']] },
      { t: 'High country and a long lunch', s: 'Viewpoints and valley roads', ev: [['07:30', 'Drive up to a high viewpoint above the cloud line. Bring a layer.'], ['11:00', 'Stop at a spice garden and a small village market on the way down.'], ['13:30', 'A long lunch served on banana leaf, cooked by the host family.']] },
      { t: 'A day with a local family', s: 'Cooking, markets and unhurried conversation', ev: [['09:00', 'Walk to a nearby home for a cooking session.'], ['12:30', 'Eat what you cooked, together.'], ['19:00', 'Dinner at your hill resort, a change of scene for your last two nights.']] },
      { t: 'A slow day', s: 'No fixed plan, with options if you want them', ev: [['10:30', 'Choose a guided nature walk, a gentle cycle ride along the estate roads, or nothing at all.'], ['18:30', 'Farewell dinner, with your planner on call for any last changes.']] },
      { t: 'Departure', s: 'Munnar to Kochi, or onward', ev: [['08:00', 'Breakfast and a last walk around the grounds.'], ['09:30', 'Drive back down to Kochi for your flight or onward journey.']] }
    ]
  });

  add({
    slug: 'alleppey-private-houseboat-escape', name: 'Alleppey Private Houseboat Escape', dest: 'Alleppey and Kumarakom, Kerala', region: 'Backwaters', cats: 'backwaters', nights: 4, usd: 1180, inr: 104000, senior: true,
    short: 'A private houseboat, a village canoe and meals cooked on board.',
    card: 'images/pkg-alleppey.jpg', hero: L('boat'), heroAlt: 'A houseboat drifting through palm-lined backwaters near Kumarakom',
    lead: 'A web of lagoons and canals, best seen from the water.',
    overview: ['Four nights on a private houseboat with its own crew and cook, drifting through lagoons, canals and rice paddies at the pace of the current.', 'Between cruises there are village walks, a canoe trip with a local boatman and meals prepared on board from whatever the market had that morning.'],
    glance: [['Starts and ends', 'Kochi'], ['Best months', 'October to March'], ['Pace', 'Unhurried, mostly by boat'], ['Where you stay', 'Private houseboat, lakeside homestay'], ['Travelling as', 'Private, for your party only']],
    gallery: [G('images/pkg-alleppey.jpg', 'Travellers on a boat in the Kerala backwaters', 'On the water'), G(L('boat', 800, 600), 'A houseboat on a quiet lagoon', 'A quiet lagoon at Kumarakom'), G('images/pkg-meal.jpg', 'A Kerala meal served on a banana leaf', 'Lunch cooked on board')],
    include: ['Four nights on a private houseboat with crew and cook', 'All meals on board', 'A village canoe trip with a local boatman', 'Guided walk along the paddy embankments', 'Private vehicle and driver for transfers', 'One named planner, on call from booking to departure'],
    days: [
      { t: 'Arrive and board your houseboat', s: 'Transfer from Kochi, then onto the water', ev: [['11:00', 'Pick-up and a short drive to the jetty.'], ['13:00', 'Welcome drink and lunch as you set off.'], ['18:30', 'Moor for the evening and watch the light fade.']] },
      { t: 'Canals and village life', s: 'Smaller boats, narrower waterways', ev: [['08:00', 'Breakfast on deck.'], ['10:30', 'Village canoe with a local boatman.'], ['19:30', 'Dinner cooked on board.']] },
      { t: 'A slow day on the lake', s: 'Open water, no schedule', ev: [['09:00', 'Cruise at your own pace.'], ['16:00', 'Optional stop at a village toddy shop (non-alcoholic options available).']] },
      { t: 'Paddy fields and a family lunch', s: 'Walks along the embankments', ev: [['09:30', 'Guided walk along the paddy bunds.'], ['13:00', 'Lunch with a host family.']] },
      { t: 'Disembark and depart', s: 'Back to Kochi or onward', ev: [['09:00', 'Breakfast, then disembark.'], ['10:30', 'Transfer to Kochi airport or your next stop.']] }
    ]
  });

  add({
    slug: 'varkala-cliffside-calm', name: 'Varkala Cliffside Calm', dest: 'Varkala, Kerala', region: 'Coast', cats: 'coast', nights: 5, usd: 1360, inr: 120000, senior: false,
    short: 'A cliff-top villa, quiet beaches and dinners by the fishing boats.',
    card: 'images/pkg-varkala.jpg', hero: 'images/pkg-varkala.jpg', heroAlt: "Varkala's red cliffs and quiet beach",
    lead: 'Red cliffs, quiet beaches and long evenings by the sea.',
    overview: ['Five nights in a cliff-top villa above the Arabian Sea, with a beach path to the sand below and fishing boats pulling in at dusk.', 'Mornings are for swimming and walking the cliff path. Afternoons are open. Dinner is whatever came in on the boats.'],
    glance: [['Starts and ends', 'Thiruvananthapuram'], ['Best months', 'November to March'], ['Pace', 'Easy, with room to do nothing'], ['Where you stay', 'Cliff villa, beach cottage'], ['Travelling as', 'Private, for your party only']],
    gallery: [G('images/pkg-varkala.jpg', "Varkala's red cliffs and quiet beach", 'The cliff path at Varkala'), G(L('lighthouse', 800, 600), 'The Kovalam lighthouse above the palm-lined coast', 'The lighthouse coast'), G('images/pkg-meal.jpg', 'A Kerala meal served on a banana leaf', 'Dinner, the Kerala way')],
    include: ['Five nights in a cliff-top villa, with daily breakfast', 'A morning swim and cliff walk with a local guide', 'A fishing-village visit and a seafood dinner', 'Airport transfers in Thiruvananthapuram', 'Private vehicle and driver for day trips', 'One named planner, on call from booking to departure'],
    days: [
      { t: 'Arrive on the coast', s: 'Thiruvananthapuram to Varkala, about 1 hour by road', ev: [['12:00', 'Airport pick-up and a coastal drive to Varkala.'], ['15:00', 'Settle into your cliff villa. Tea on the terrace.'], ['18:30', 'Sunset from the cliff path.']] },
      { t: 'The cliff and the sea', s: 'A swim, a walk and a long lunch', ev: [['07:30', 'Walk the cliff path with a local guide before the heat.'], ['10:00', 'Swim at a quieter stretch of beach.'], ['19:30', 'Dinner of the day\'s catch.']] },
      { t: 'A fishing village', s: 'Boats, nets and morning markets', ev: [['06:30', 'Watch the boats come in and the auction on the sand.'], ['11:00', 'Breakfast with a fisher family.']] },
      { t: 'A slow day', s: 'Nothing planned', ev: [['10:00', 'Read, swim or rest. Your planner can arrange a spa visit or a short drive if you like.']] },
      { t: 'The wider coast', s: 'A day trip along the shoreline', ev: [['09:00', 'Drive south along the coast with stops at quiet beaches.'], ['17:00', 'Back at the villa for sunset.']] },
      { t: 'Departure', s: 'Varkala to Thiruvananthapuram', ev: [['09:00', 'Breakfast and a last look at the sea.'], ['10:30', 'Transfer to the airport or your onward journey.']] }
    ]
  });

  add({
    slug: 'kerala-wellness-retreat', name: 'Kerala Wellness Retreat', dest: 'Kerala coast', region: 'Wellness', cats: 'wellness', nights: 7, usd: 2150, inr: 189000, senior: true,
    short: 'Daily yoga, restorative meals and a lot of unscheduled time.',
    card: 'images/pkg-wellness.jpg', hero: 'images/pkg-wellness.jpg', heroAlt: 'Morning yoga by the sea',
    lead: 'A week built around rest, movement and good food.',
    overview: ['Seven nights by the sea with a gentle daily rhythm: yoga and breathing at first light, a long lunch of seasonal Kerala food, and the afternoon left empty.', 'This is a stay for calm and restoration. It is not medical treatment, and there is nothing to achieve.'],
    glance: [['Starts and ends', 'Thiruvananthapuram'], ['Best months', 'October to March'], ['Pace', 'Slow, with a gentle daily rhythm'], ['Where you stay', 'Seafront retreat'], ['Travelling as', 'Private or as a pair']],
    gallery: [G('images/pkg-wellness.jpg', 'Morning yoga by the sea', 'Morning practice by the shore'), G('images/pkg-meal.jpg', 'A Kerala meal served on a banana leaf', 'Seasonal Kerala meals'), G(L('leaf', 800, 600), 'A Kerala breakfast served on a banana leaf', 'Breakfast, unhurried')],
    include: ['Seven nights at a seafront retreat, with all meals', 'Daily yoga and breathing sessions with local teachers', 'Spa time and quiet gardens', 'Airport transfers in Thiruvananthapuram', 'A planner check-in each day', 'One named planner, on call from booking to departure'],
    days: [
      { t: 'Arrival and a quiet evening', s: 'Airport to the retreat', ev: [['13:00', 'Airport pick-up and a short drive to the coast.'], ['17:30', 'A walk on the beach, then an early dinner.']] },
      { t: 'The first morning', s: 'Settling into the rhythm', ev: [['06:30', 'Yoga and breathing on the terrace.'], ['12:30', 'A long, seasonal lunch.'], ['17:30', 'Evening walk.']] },
      { t: 'Movement and rest', s: 'A gentle session and a free afternoon', ev: [['06:30', 'Morning practice with your teacher.'], ['15:00', 'Spa time or simply rest.']] },
      { t: 'A day by the water', s: 'Sea, shade and silence', ev: [['07:00', 'A quiet beach morning.'], ['13:00', 'Lunch in the garden.']] },
      { t: 'Out into the village', s: 'A short, easy outing', ev: [['09:30', 'A walk to a local market and a visit to a family kitchen.'], ['17:30', 'Back for evening practice.']] },
      { t: 'A slow day', s: 'Nothing planned', ev: [['10:00', 'Do as much or as little as you like.']] },
      { t: 'The last full day', s: 'A farewell evening', ev: [['06:30', 'Final morning session.'], ['19:00', 'Lamp-lit farewell dinner.']] },
      { t: 'Departure', s: 'Back to the airport', ev: [['09:00', 'Breakfast, then transfer to the airport or onward.']] }
    ]
  });

  add({
    slug: 'heritage-and-temple-towns', name: 'Heritage and Temple Towns', dest: 'Northern Kerala', region: 'Culture', cats: 'culture', nights: 6, usd: 1540, inr: 135000, senior: false,
    short: 'Old palaces, lamp-lit temple evenings and a guide who grew up nearby.',
    card: U(ID.theyyam, 800, 900), hero: L('theyyam'), heroAlt: 'A Theyyam ritual performance lit by fire',
    lead: 'Old towns, temple evenings and rituals that are still alive.',
    overview: ['Six nights across the old towns of northern Kerala, with palaces, temple courtyards and a guide who grew up in the region.', 'If your dates fall in the ritual season, your planner will try to arrange an evening at a Theyyam performance. Performances depend on the local calendar and are not guaranteed.'],
    glance: [['Starts and ends', 'Kozhikode'], ['Best months', 'November to March'], ['Pace', 'Steady, with short drives'], ['Where you stay', 'Heritage homes, small hotels'], ['Travelling as', 'Private, for your party only']],
    gallery: [G(L('theyyam', 1000, 800), 'A Theyyam ritual performance lit by fire', 'A Theyyam evening'), G(L('theyyam', 800, 600), 'A Theyyam performer in full costume', 'Costume and fire'), G('images/pkg-meal.jpg', 'A Kerala meal served on a banana leaf', 'A temple-town lunch')],
    include: ['Six nights in heritage homes and small hotels, with daily breakfast', 'A local guide throughout', 'Palace and temple-courtyard visits', 'Private vehicle and driver', 'Airport transfers', 'One named planner, on call from booking to departure'],
    days: [
      { t: 'Arrival in Kozhikode', s: 'Settle into the old town', ev: [['14:00', 'Pick-up and check-in at a heritage home.'], ['17:30', 'An evening walk through the old market streets.']] },
      { t: 'Palaces and courtyards', s: 'A day with your guide', ev: [['09:00', 'Visit an old palace and its museum.'], ['17:00', 'A temple courtyard at lamp-lighting time.']] },
      { t: 'The weavers', s: 'Craft villages', ev: [['10:00', 'Watch handloom weavers at work.'], ['13:00', 'Lunch with a local family.']] },
      { t: 'North to Kannur', s: 'A scenic drive', ev: [['08:30', 'Drive north along the coast.'], ['18:00', 'Arrive and settle in.']] },
      { t: 'Ritual evening', s: 'Subject to the local calendar', ev: [['19:00', 'If a Theyyam performance is being held, attend with your guide.']] },
      { t: 'A slow morning', s: 'Rest and a late start', ev: [['11:00', 'Free time, then a visit to an old fort.']] },
      { t: 'Departure', s: 'To the airport or onward', ev: [['09:00', 'Breakfast, then transfer to the airport or your next stop.']] }
    ]
  });

  add({
    slug: 'highlands-to-backwaters-grand-loop', name: 'Highlands to Backwaters Grand Loop', dest: 'Munnar to Alleppey, Kerala', region: 'Highlands', cats: 'highlands backwaters', nights: 9, usd: 2480, inr: 218000, senior: false,
    short: 'Cool hills first, then the water. The full arc of Kerala in nine nights.',
    card: U(ID.gardens, 800, 900), hero: L('gardens'), heroAlt: 'Tea gardens of the Kannan Devan hills',
    lead: 'Cool hills first, then the water.',
    overview: ['Nine nights that follow the land downhill: five in the Munnar tea country, then four on the backwaters, with a private driver and one planner throughout.', 'It is the full arc of Kerala without the rushing. Each place gets enough time to feel like somewhere you have been.'],
    glance: [['Starts and ends', 'Kochi'], ['Best months', 'November to March'], ['Pace', 'Slow, with two long drives'], ['Where you stay', 'Estate bungalow, private houseboat'], ['Travelling as', 'Private, for your party only']],
    gallery: [G(L('gardens', 1000, 800), 'Tea gardens of the Kannan Devan hills', 'The tea country'), G(L('boat', 800, 600), 'A houseboat on a quiet lagoon', 'Then the water'), G(L('picker', 800, 600), 'A tea picker harvesting on a Munnar estate', 'Where the tea begins')],
    include: ['Five nights in the hills and four nights on a private houseboat', 'Daily breakfast, and all meals on the houseboat', 'Guided estate walks and a village canoe trip', 'Private vehicle and driver for the whole journey', 'Airport transfers in Kochi', 'One named planner, on call from booking to departure'],
    days: [
      { t: 'Kochi to Munnar', s: 'Up into the hills', ev: [['10:00', 'Airport pick-up and the drive up to Munnar.']] },
      { t: 'The tea estates', s: 'A guided walk', ev: [['07:00', 'Walk the terraces with an estate guide.']] },
      { t: 'High country', s: 'Viewpoints and valley roads', ev: [['07:30', 'A high viewpoint above the cloud line.']] },
      { t: 'A day with a local family', s: 'Cooking and conversation', ev: [['09:00', 'A cooking session at a nearby home.']] },
      { t: 'A slow hill day', s: 'No plan', ev: [['10:30', 'Rest, or a gentle walk.']] },
      { t: 'Down to the backwaters', s: 'Munnar to Alleppey', ev: [['09:00', 'Drive down to the water.'], ['15:00', 'Board your private houseboat.']] },
      { t: 'Canals and village life', s: 'Smaller boats', ev: [['10:30', 'Canoe with a local boatman.']] },
      { t: 'A slow day on the lake', s: 'Open water', ev: [['09:00', 'Cruise at your own pace.']] },
      { t: 'Paddy fields and a family lunch', s: 'Embankment walks', ev: [['09:30', 'Guided walk, then lunch with a host family.']] },
      { t: 'Disembark and depart', s: 'Back to Kochi', ev: [['09:00', 'Breakfast, then transfer to Kochi airport.']] }
    ]
  });

  /* ordering for the home page / "you may also like" */
  var order = Object.keys(T);
  w.LuxyCatalog = {
    tours: T,
    order: order,
    get: function (slug) { return T[slug] || null; },
    /* related packages: same region/category first, then the rest; never the current package; hidden if fewer than 2 */
    related: function (slug, max) {
      var cur = T[slug]; if (!cur) return [];
      var mine = cur.cats.split(' ');
      var list = order.filter(function (s) { return s !== slug; }).map(function (s) { return T[s]; });
      list.sort(function (a, b) {
        var sa = a.cats.split(' ').some(function (c) { return mine.indexOf(c) > -1; }) ? 0 : 1;
        var sb = b.cats.split(' ').some(function (c) { return mine.indexOf(c) > -1; }) ? 0 : 1;
        return sa - sb;
      });
      return list.slice(0, max || 3);
    }
  };

  /* ---------- destinations for the customizable package builder (customize.html) ---------- */
  var DEST = [
    { id: 'fort-kochi', name: 'Fort Kochi', region: 'Coast', blurb: 'An old harbour town of Chinese fishing nets, spice lanes and colonial streets.',
      places: [['Chinese Fishing Nets','₹500','$6'],['Mattancherry Palace','₹700','$8'],['Jew Town','₹400','$5'],['St. Francis Church','₹300','$4'],['Fort Kochi Beach','₹0','$0'],['Paradesi Synagogue','₹1,000','$12']],
      hotels: [['Fort House Hotel','₹6,500','$78'],['Brunton Boatyard','₹18,000','$215'],['Old Harbour Hotel','₹8,000','$96'],['Tea Bungalow','₹12,500','$150'],['Le Maritime','₹7,000','$84']] },
    { id: 'munnar', name: 'Munnar', region: 'Highlands', blurb: 'Tea estates, misty ridges and cool mornings.',
      places: [['Tea Museum','₹500','$6'],['Mattupetty Dam','₹600','$7'],['Eravikulam National Park','₹2,000','$24'],['Top Station','₹400','$5'],['Echo Point','₹300','$4'],['Photo Point','₹200','$2']],
      hotels: [['The Panoramic','₹7,500','$90'],['Tea County','₹6,800','$82'],['Clouds Valley Leisure Hotel','₹8,500','$102'],['Blanket Hotel & Spa','₹12,000','$144'],['Fragrant Nature','₹10,500','$126']] },
    { id: 'thekkady', name: 'Thekkady', region: 'Highlands', blurb: 'Spice plantations and forest walks around Periyar.',
      places: [['Periyar Wildlife Sanctuary','₹1,200','$14'],['Bamboo Rafting','₹2,500','$30'],['Spice Plantation Tour','₹800','$10'],['Mangala Devi Temple','₹600','$7'],['Elephant Junction','₹1,500','$18'],['Kadathanadan Cultural Centre','₹700','$8']],
      hotels: [['The Elephant Court','₹8,500','$102'],['Greenwoods Resort','₹7,200','$86'],['Mundakal Thekkady','₹9,500','$114'],['Kumily Gate Hotel','₹6,500','$78'],['Cardamom County','₹11,000','$132']] },
    { id: 'vagamon', name: 'Vagamon', region: 'Highlands', blurb: 'Rolling meadows and pine forests, quieter than Munnar.',
      places: [['Vagamon Meadows','₹300','$4'],['Pine Forest','₹200','$2'],['Thangal Para','₹400','$5'],['Vagamon Falls','₹500','$6'],['Kurishumala','₹300','$4'],['Marmala Falls','₹600','$7']],
      hotels: [['Vagamon Hideout','₹6,500','$78'],['Winter Vale Green','₹7,800','$94'],['Elixir Hills','₹10,500','$126'],['Green Meadows','₹5,900','$71'],['Vistara Vagamon','₹8,200','$98']] },
    { id: 'wayanad', name: 'Wayanad', region: 'Highlands', blurb: 'Forested hills, waterfalls and village life.',
      places: [['Edakkal Caves','₹800','$10'],['Pookode Lake','₹500','$6'],['Soochipara Falls','₹1,000','$12'],['Banasura Sagar Dam','₹700','$8'],['Chembra Peak','₹1,500','$18'],['Muthanga Wildlife Sanctuary','₹900','$11']],
      hotels: [['The Windflower Resort','₹11,500','$138'],['Wayanad Coffee County','₹7,500','$90'],['Pepper Trail','₹9,800','$118'],['Vythiri Village','₹12,500','$150'],['Sterling Lillium','₹8,900','$107']] },
    { id: 'athirappilly', name: 'Athirappilly', region: 'Nature', blurb: 'One of Kerala\'s best-known waterfalls, set in rainforest.',
      places: [['Athirappilly Falls','₹1,200','$14'],['Vazhachal Falls','₹700','$8'],['Charpa Falls','₹400','$5'],['Sholayar Forest','₹1,000','$12'],['Thumboormuzhy Dam','₹300','$4'],['Kauthuka Park','₹500','$6']],
      hotels: [['Rainforest Resort','₹9,500','$114'],['Athirappilly Green Trees','₹7,500','$90'],['Riverok Resort','₹8,800','$106'],['Silver Storm Resort','₹6,900','$83'],['Flora Misty Falls','₹10,500','$126']] },
    // { id: 'alleppey', name: 'Alleppey', region: 'Backwaters', blurb: 'Canals, lagoons and houseboats at water level.' },
    // { id: 'kumarakom', name: 'Kumarakom', region: 'Backwaters', blurb: 'Quiet lakeside stays and birdlife on Vembanad.' },
    // { id: 'varkala', name: 'Varkala', region: 'Coast', blurb: 'Red cliffs above the sea and a beach path below.' },
    // { id: 'kovalam', name: 'Kovalam', region: 'Coast', blurb: 'Crescent beaches and a lighthouse on the headland.' },
    // { id: 'bekal', name: 'Bekal', region: 'Coast', blurb: 'A coastal fort and quiet northern beaches.' },
    // { id: 'kannur', name: 'Kannur', region: 'Culture', blurb: 'Handloom villages and, in season, Theyyam rituals.' }
  ];
  w.LuxyCatalog.destinations = DEST;
  w.LuxyCatalog.destination = function (id) { return DEST.filter(function (d) { return d.id === id; })[0] || null; };
  w.LuxyCatalog.MAX_CUSTOM_DAYS = 6;      /* the customizable builder currently allows up to 6 days */
})(window);
