/**
 * Fills the ChamberQ setup Google Form. Used by the agent after you sign in,
 * or paste into https://script.google.com → New project → Run buildChamberQForm.
 *
 * Labels: English first, Bangla in brackets.
 */
function buildChamberQForm() {
  const form = FormApp.openById('1onIMvjngiBHIaYX7q0MR_32code9II16Mv2C82mBcQI');

  form.setTitle('Chamber setup — ChamberQ (চেম্বার সেটআপ)');
  form.setDescription(
    'Sitting times plus one photo of the doctor is enough to turn online booking on. Website copy can wait. Reception staff can fill this. (সিটিং সময় আর ডাক্তারের একটা ছবি থাকলেই অনলাইন বুকিং চালু করা যায়। ওয়েবসাইটের লেখা পরেও চলবে। রিসেপশন স্টাফ পূরণ করতে পারেন।)'
  );
  form.setLimitOneResponsePerUser(false);
  form.setCollectEmail(false);
  form.setAllowResponseEdits(true);
  form.setShowLinkToRespondAgain(false);
  form.setProgressBar(true);

  const existing = form.getItems();
  for (let i = existing.length - 1; i >= 0; i--) {
    form.deleteItem(existing[i]);
  }

  form.addPageBreakItem().setTitle('You and the doctor (আপনি ও ডাক্তার)');

  form.addTextItem().setTitle('What name will patients see for the chamber? (রোগী চেম্বারের কোন নাম দেখবে?)').setHelpText('As on the visiting card. Example: Dr. Karim’s Chamber (ভিজিটিং কার্ডে যা লেখা। যেমন: ডা. করিমের চেম্বার)').setRequired(true);
  form.addTextItem().setTitle('Doctor’s full name (ডাক্তারের পুরো নাম)').setHelpText('As printed on the pad. Example: Dr. A. K. M. Karim (প্যাডে যেভাবে ছাপা)').setRequired(true);
  form.addTextItem().setTitle('Degrees (ডিগ্রি)').setHelpText('Example: MBBS, FCPS (Medicine)').setRequired(true);
  form.addTextItem().setTitle('BM&DC registration number (বিএমডিসি রেজিস্ট্রেশন নম্বর)').setHelpText('Leave blank if you do not have it (না থাকলে খালি রাখুন)').setRequired(false);
  form.addMultipleChoiceItem().setTitle('What type of practice? (কোন ধরনের প্র্যাকটিস?)').setChoiceValues([
    'General (জেনারেল)',
    'Gynae (গাইনি)',
    'Dental (দাঁত)',
    'Child (শিশু)',
    'Heart (হার্ট)',
    'Skin (চর্ম)',
    'Other (অন্য)',
  ]).setRequired(true);
  form.addTextItem().setTitle('Usual visit fee in taka? (সাধারণ ভিজিট ফি কত টাকা?)').setHelpText('Numbers only. Example: 800 (শুধু সংখ্যা)').setRequired(true);
  form.addParagraphTextItem().setTitle('Any different follow-up or other fees? (ফলো-আপ বা অন্য ফি আলাদা কি?)').setHelpText('Leave blank if the same. If different: Follow-up 500, dressing 300 (একই হলে খালি। আলাদা হলে লিখুন)').setRequired(false);
  form.addTextItem().setTitle('Phone number patients should call (রোগী যে নম্বরে ফোন করবে)').setHelpText('01XXXXXXXXX').setRequired(true);
  form.addTextItem().setTitle('WhatsApp number (হোয়াটসঅ্যাপ নম্বর)').setHelpText('Write it again even if it is the same as the phone (ফোন আর হোয়াটসঅ্যাপ একই হলে আবার লিখুন)').setRequired(true);
  form.addTextItem().setTitle('Doctor login email (ডাক্তার লগইনের ইমেইল)').setHelpText('Gmail is fine. We will send the password here. (জিমেইল চলবে। এই ইমেইলে পাসওয়ার্ড পাঠাব।)').setRequired(true);
  form.addTextItem().setTitle('Preferred page link? (পেজের লিংক কেমন চান?)').setHelpText('Short English name. Example: drkarim → chamberq.com/drkarim. Leave blank and we will make one from the name. (ছোট ইংরেজি নাম। খালি রাখলে আমরা নাম থেকে বানাব।)').setRequired(false);
  form.addMultipleChoiceItem().setTitle('Patient page language? (রোগীর পেজ কোন ভাষায়?)').setChoiceValues([
    'Bangla (বাংলা)',
    'English (ইংরেজি)',
  ]).setRequired(true);
  form.addMultipleChoiceItem().setTitle('Who will run the waiting-room queue? (ওয়েটিং রুমের লাইন কে চালাবে?)').setHelpText('Who taps “next patient” (যে “পরবর্তী রোগী” চাপবে)').setChoiceValues([
    'Reception staff (রিসেপশন স্টাফ)',
    'The doctor (ডাক্তার নিজে)',
  ]).setRequired(true);
  form.addCheckboxItem().setTitle('Which parts do you want? (কোন কোন অংশ চান?)').setChoiceValues([
    'Website and online booking (ওয়েবসাইট ও অনলাইন বুকিং)',
    'Waiting-room TV — live queue (ওয়েটিং রুম টিভি — লাইভ কিউ)',
    'Prescription (প্রেসক্রিপশন)',
  ]).setRequired(true);
  form.addMultipleChoiceItem().setTitle('Send SMS after a booking? (বুকিংয়ের পর রোগীকে SMS যাবে?)').setHelpText('SMS uses prepaid credits. Booking still works if the wallet is empty — they just get no text. (SMS আলাদা ক্রেডিট। খালি থাকলেও বুকিং হবে, শুধু টেক্সট যাবে না।)').setChoiceValues([
    'Yes, I want SMS (হ্যাঁ, SMS চাই)',
    'No, skip SMS (না, লাগবে না)',
  ]).setRequired(true);

  form.addPageBreakItem().setTitle('Where is the chamber (চেম্বার কোথায়)');
  form.addTextItem().setTitle('Name of this chamber (এই চেম্বারের নাম)').setHelpText('The name patients already use. Example: Dhanmondi chamber / Popular Diagnostic (রোগী যে নামে চেনে। যেমন: ধানমন্ডি চেম্বার / পপুলার ডায়াগনস্টিক)').setRequired(true);
  form.addParagraphTextItem().setTitle('Full address (পুরো ঠিকানা)').setHelpText('Road, house, area, city (রোড, বাড়ি, এলাকা, শহর)').setRequired(true);
  form.addTextItem().setTitle('Google Maps link (গুগল ম্যাপ লিংক)').setHelpText('Phone: Maps → Share → Copy link → paste here. Not a Facebook page. (ফোনে Maps খুলুন → Share → Copy link। ফেসবুক পেজের লিংক নয়।)').setRequired(true);
  form.addParagraphTextItem().setTitle('Any other chambers? (আর কোন চেম্বার আছে?)').setHelpText('Leave blank if none. If yes: name + address for each. Solo plan: max 5 locations. (না থাকলে খালি। থাকলে প্রতিটার নাম + ঠিকানা। সোলো প্ল্যানে সর্বোচ্চ ৫টা লোকেশন।)').setRequired(false);

  form.addPageBreakItem().setTitle('Sitting days — most important (কোন দিন বসেন — সবচেয়ে জরুরি)');
  form.addParagraphTextItem().setTitle('Write sitting times — one sitting per line (সিটিং সময় লিখুন — এক লাইনে একটা)').setHelpText('Saturday morning and Saturday evening = two lines. Like cinema showtimes.\n(শনিবার সকাল আর শনিবার সন্ধ্যা = দুই লাইন। সিনেমার শোর মতো।)\n\nDay | Morning/Afternoon/Evening | Start | End | How many serials\nSaturday | Evening | 5:00 | 8:00 | 20\nSunday | Evening | 5:00 | 8:00 | 20\n(শনিবার | সন্ধ্যা | ৫:০০ | ৮:০০ | ২০)\n\nIf Friday is closed: Friday off (শুক্রবার বন্ধ)').setRequired(true);
  form.addTextItem().setTitle('After online serials fill, how many extra walk-ins can the desk take? (অনলাইন সিরিয়াল শেষ হলে ডেস্ক আর কয়জন ওয়াক-ইন নিতে পারে?)').setHelpText('0 if none. Example: 5 (না নিলে ০)').setRequired(false);

  form.addPageBreakItem().setTitle('Staff (স্টাফ)');
  form.addParagraphTextItem().setTitle('Will anyone at the counter log in? (কাউন্টারের কেউ লগইন করবে?)').setHelpText('Name + Gmail. Leave blank if none — the doctor can run it alone. (নাম + জিমেইল। না থাকলে খালি — ডাক্তার একা চালাতে পারবেন।)').setRequired(false);

  form.addPageBreakItem().setTitle('Photos (ছবি)');
  form.addParagraphTextItem().setTitle('Will you send photos on WhatsApp, or here? (ছবি হোয়াটসঅ্যাপে পাঠাবেন, নাকি এখানে?)').setHelpText('A face photo of the doctor is required. Add File upload in the form editor if you want photos attached here. (ডাক্তারের মুখের ছবি জরুরি।)').setRequired(false);

  form.addPageBreakItem().setTitle('Website copy (can wait) (ওয়েবসাইটের লেখা — পরেও চলবে)');
  form.addTextItem().setTitle('One-line introduction (এক লাইনে পরিচয়)').setHelpText('Example: Chest specialist, Dhanmondi — book online, pay at the chamber (যেমন: ধানমন্ডির বক্ষব্যাধি বিশেষজ্ঞ — অনলাইনে সিরিয়াল, চেম্বারে পেমেন্ট)').setRequired(false);
  form.addParagraphTextItem().setTitle('About the doctor — 2 to 4 lines (ডাক্তার সম্পর্কে ২–৪ লাইন)').setRequired(false);
  form.addParagraphTextItem().setTitle('Which problems do you see? (কোন কোন সমস্যা দেখেন?)').setHelpText('What patients search. Example: asthma, diabetes follow-up, pregnancy checkup (রোগী যা সার্চ করে। যেমন: অ্যাজমা, ডায়াবেটিস ফলো-আপ, প্রেগন্যান্সি চেকআপ)').setRequired(false);
  form.addParagraphTextItem().setTitle('Three questions patients often ask (রোগীরা যা জিজ্ঞেস করে — ৩টা)').setHelpText('Example: Do you take walk-ins? What should they bring? What if they cancel? (যেমন: ওয়াক-ইন চলে? কী নিয়ে আসবে? বাতিল করলে কী হবে?)').setRequired(false);
}
