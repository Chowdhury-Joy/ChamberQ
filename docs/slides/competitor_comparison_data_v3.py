"""Feature matrix v3 — 2 mid-close + 4 distant rivals, partial %, buildable gaps."""

Y, P, N = "yes", "partial", "no"

# Mid-close first, then distant (left → right after ChamberQ).
COMPETITORS_V3 = [
    ("eDoctorBD", "edoctor"),
    ("ProtonEMR", "proton"),
    ("PrescriptionSoftwareBD", "rxswbd"),
    ("Bissoy Serial", "bissoy"),
    ("Doctors Care", "doctorscare"),
    ("DPAS (Solvers)", "dpas"),
]

# ChamberQ column footnotes (†) on our strengths page only.
CQ_FEATURE_FOOTNOTES = {
    "Bangla patient-facing UI": "† paid add-on",
    "Lab tests added during online booking (Clinic tier)": "† Clinic plan only",
    "Booking confirmation SMS (prepaid wallet)": "† prepaid SMS credits",
    "Multi-chamber schedules (different days/locations)": "† up to 5 on Solo",
}

# Status: Y, N, or int 1–60 (= Partial N%)
FEATURES_V3 = [
    (
        "Branded patient experience",
        [
            ("Branded patient website (doctor's own page)", {
                "edoctor": Y, "proton": N, "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N,
            }),
            ("Website page builder (hero, conditions, FAQ, etc.)", {
                "edoctor": 35, "proton": N, "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N,
            }),
            ("Custom domain support", {
                "edoctor": 45, "proton": N, "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N,
            }),
            ("Bangla patient-facing UI", {
                "edoctor": 40, "proton": 35, "rxswbd": 30, "bissoy": 30, "doctorscare": 45, "dpas": 30,
            }),
        ],
    ),
    (
        "Booking & serials",
        [
            ("Online serial booking", {
                "edoctor": Y, "proton": Y, "rxswbd": Y, "bissoy": 40, "doctorscare": Y, "dpas": Y,
            }),
            ("Session capacity / seat limits", {
                "edoctor": Y, "proton": Y, "rxswbd": 40, "bissoy": 35, "doctorscare": Y, "dpas": 40,
            }),
            ("Multi-chamber schedules (different days/locations)", {
                "edoctor": Y, "proton": Y, "rxswbd": Y, "bissoy": N, "doctorscare": 45, "dpas": Y,
            }),
            ("Walk-ins and online bookings in one queue", {
                "edoctor": 50, "proton": Y, "rxswbd": 40, "bissoy": Y, "doctorscare": Y, "dpas": 40,
            }),
            ("Household picker (same phone, pick the person)", {
                "edoctor": N, "proton": N, "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N,
            }),
            ("Block duplicate booking same person same day", {
                "edoctor": 45, "proton": 40, "rxswbd": 40, "bissoy": N, "doctorscare": 45, "dpas": 40,
            }),
            ("Vacation / day off — auto-cancel + patient notice", {
                "edoctor": 45, "proton": 40, "rxswbd": 35, "bissoy": N, "doctorscare": 40, "dpas": 35,
            }),
            ("Lab tests added during online booking (Clinic tier)", {
                "edoctor": 40, "proton": N, "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N,
            }),
            ("Pay at chamber (no payment gateway on booking)", {
                "edoctor": 50, "proton": Y, "rxswbd": Y, "bissoy": N, "doctorscare": 45, "dpas": 45,
            }),
        ],
    ),
    (
        "Queue & waiting room",
        [
            ("Live outdoor / TV queue screen", {
                "edoctor": 40, "proton": Y, "rxswbd": N, "bissoy": Y, "doctorscare": N, "dpas": N,
            }),
            ("Voice serial call-out on the screen", {
                "edoctor": N, "proton": 35, "rxswbd": N, "bissoy": Y, "doctorscare": N, "dpas": N,
            }),
            ("Shareable patient ticket (link + WhatsApp)", {
                "edoctor": 40, "proton": 45, "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N,
            }),
            ("Live queue position + estimated wait time", {
                "edoctor": N, "proton": 40, "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N,
            }),
            ("\"Doctor is late\" SMS to everyone waiting", {
                "edoctor": N, "proton": N, "rxswbd": N, "bissoy": 35, "doctorscare": N, "dpas": N,
            }),
            ("Call next / patient arrived / in chamber workflow", {
                "edoctor": 50, "proton": Y, "rxswbd": 40, "bissoy": 40, "doctorscare": 45, "dpas": 40,
            }),
            ("Call out of turn + reinstate no-show", {
                "edoctor": N, "proton": 40, "rxswbd": N, "bissoy": 40, "doctorscare": N, "dpas": N,
            }),
            ("Doctor or staff runs queue (configurable)", {
                "edoctor": 40, "proton": 45, "rxswbd": N, "bissoy": N, "doctorscare": 45, "dpas": N,
            }),
        ],
    ),
    (
        "Patient communication",
        [
            ("WhatsApp share — ticket, cancel, prescription (free)", {
                "edoctor": N, "proton": Y, "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N,
            }),
            ("Per-doctor toggles (SMS / WhatsApp per stage)", {
                "edoctor": 40, "proton": 40, "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N,
            }),
            ("Patient portal — look up bookings by phone", {
                "edoctor": Y, "proton": 45, "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N,
            }),
            ("Booking confirmation SMS (prepaid wallet)", {
                "edoctor": Y, "proton": Y, "rxswbd": 35, "bissoy": 40, "doctorscare": Y, "dpas": 35,
            }),
        ],
    ),
    (
        "Clinical & prescriptions",
        [
            ("Digital prescriptions + print", {
                "edoctor": Y, "proton": Y, "rxswbd": Y, "bissoy": N, "doctorscare": Y, "dpas": Y,
            }),
            ("Consult screen (clinical context while patient is in)", {
                "edoctor": 50, "proton": 45, "rxswbd": N, "bissoy": N, "doctorscare": 45, "dpas": N,
            }),
            ("Complete visit, then call next (hand Rx before next patient)", {
                "edoctor": N, "proton": 40, "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N,
            }),
            ("Staff can type paper prescription for doctor", {
                "edoctor": N, "proton": N, "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N,
            }),
            ("Voice note on visit (private storage)", {
                "edoctor": N, "proton": N, "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N,
            }),
            ("Photo of paper prescription slip (private)", {
                "edoctor": 45, "proton": N, "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N,
            }),
            ("Short prescription share link (SMS-friendly)", {
                "edoctor": 40, "proton": Y, "rxswbd": N, "bissoy": N, "doctorscare": 45, "dpas": N,
            }),
            ("Diagnosis picker (coded + free text)", {
                "edoctor": 50, "proton": 45, "rxswbd": 40, "bissoy": N, "doctorscare": 45, "dpas": 40,
            }),
            ("Visit vitals (weight, BP) on record", {
                "edoctor": 45, "proton": 45, "rxswbd": N, "bissoy": N, "doctorscare": Y, "dpas": N,
            }),
            ("Medicine list learns doctor's favourites", {
                "edoctor": N, "proton": 50, "rxswbd": 35, "bissoy": N, "doctorscare": Y, "dpas": 35,
            }),
            ("Catch-up banner for patients missing notes today", {
                "edoctor": N, "proton": N, "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N,
            }),
        ],
    ),
    (
        "Operations & onboarding",
        [
            ("Admin / doctor / staff roles (queue vs clinical split)", {
                "edoctor": 50, "proton": 45, "rxswbd": N, "bissoy": N, "doctorscare": Y, "dpas": 40,
            }),
            ("Operational day reports (bookings, queue, completion)", {
                "edoctor": 45, "proton": 40, "rxswbd": 35, "bissoy": 35, "doctorscare": 40, "dpas": 35,
            }),
            ("Done-with-you setup (not self-serve only)", {
                "edoctor": Y, "proton": 45, "rxswbd": N, "bissoy": Y, "doctorscare": N, "dpas": Y,
            }),
            ("Multi-doctor clinic tier", {
                "edoctor": Y, "proton": 45, "rxswbd": N, "bissoy": N, "doctorscare": 45, "dpas": 40,
            }),
        ],
    ),
]

# Competitors have these; ChamberQ does not today — realistic to ship within ~2 months.
# (Excludes AI Rx, offline mode, marketplace directory — not quick builds.)
GAP_FEATURES_V3 = [
    (
        "Not in ChamberQ today — addable within ~2 months",
        [
            ("Billing & invoicing at chamber", {
                "edoctor": Y, "proton": N, "rxswbd": N, "bissoy": N, "doctorscare": Y, "dpas": N,
            }),
            ("Online payment (bKash / Nagad) at booking", {
                "edoctor": 50, "proton": N, "rxswbd": N, "bissoy": 45, "doctorscare": N, "dpas": N,
            }),
            ("Doctor mobile app (installable / PWA)", {
                "edoctor": 45, "proton": Y, "rxswbd": N, "bissoy": 40, "doctorscare": N, "dpas": N,
            }),
            ("Drug interaction warnings when prescribing", {
                "edoctor": N, "proton": Y, "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N,
            }),
            ("Large medicine database (1,000+ brands)", {
                "edoctor": 45, "proton": 50, "rxswbd": 35, "bissoy": N, "doctorscare": Y, "dpas": 35,
            }),
            ("Video telemedicine consult", {
                "edoctor": Y, "proton": Y, "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N,
            }),
            ("Patient report upload in portal", {
                "edoctor": Y, "proton": N, "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N,
            }),
            ("Self-serve signup / free trial", {
                "edoctor": N, "proton": Y, "rxswbd": N, "bissoy": N, "doctorscare": Y, "dpas": N,
            }),
        ],
    ),
]

FOOTNOTE_LINES_V3 = [
    "† Bangla homepage: paid add-on.",
    "† Lab-at-booking: Clinic plan only.",
    "† Booking SMS: prepaid wallet (not bundled in plan).",
    "† Multi-chamber: up to 5 locations on Solo.",
]
