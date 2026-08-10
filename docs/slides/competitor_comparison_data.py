"""Shared feature matrix for ChamberQ competitor comparison PDFs."""

Y, P, N = "yes", "partial", "no"

COMPETITORS = [
    ("PrescriptionSoftwareBD", "rxswbd"),
    ("Bissoy Serial", "bissoy"),
    ("Doctors Care", "doctorscare"),
    ("DPAS (Solvers)", "dpas"),
    ("Medic", "medic"),
    ("ProtonEMR", "proton"),
]

FEATURES = [
    (
        "Branded patient experience",
        [
            ("Branded patient website (doctor's own page)", {
                "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N, "medic": P, "proton": N,
            }),
            ("Website page builder (hero, conditions, FAQ, etc.)", {
                "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N, "medic": N, "proton": N,
            }),
            ("Custom domain support", {
                "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N, "medic": Y, "proton": N,
            }),
            ("Bangla patient-facing UI", {
                "rxswbd": P, "bissoy": P, "doctorscare": P, "dpas": P, "medic": P, "proton": P,
            }),
        ],
    ),
    (
        "Booking & serials",
        [
            ("Online serial booking", {
                "rxswbd": Y, "bissoy": P, "doctorscare": Y, "dpas": Y, "medic": Y, "proton": Y,
            }),
            ("Session capacity / seat limits", {
                "rxswbd": P, "bissoy": P, "doctorscare": Y, "dpas": P, "medic": Y, "proton": Y,
            }),
            ("Multi-chamber schedules (different days/locations)", {
                "rxswbd": Y, "bissoy": N, "doctorscare": P, "dpas": Y, "medic": Y, "proton": Y,
            }),
            ("Walk-ins and online bookings in one queue", {
                "rxswbd": P, "bissoy": Y, "doctorscare": Y, "dpas": P, "medic": Y, "proton": Y,
            }),
            ("Household picker (same phone, pick the person)", {
                "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N, "medic": N, "proton": N,
            }),
            ("Block duplicate booking same person same day", {
                "rxswbd": P, "bissoy": N, "doctorscare": P, "dpas": P, "medic": P, "proton": P,
            }),
            ("Vacation / day off — auto-cancel + patient notice", {
                "rxswbd": P, "bissoy": N, "doctorscare": P, "dpas": P, "medic": P, "proton": P,
            }),
            ("Lab tests added during online booking (Clinic tier)", {
                "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N, "medic": N, "proton": N,
            }),
            ("Pay at chamber (no payment gateway on booking)", {
                "rxswbd": Y, "bissoy": N, "doctorscare": P, "dpas": P, "medic": Y, "proton": Y,
            }),
        ],
    ),
    (
        "Queue & waiting room",
        [
            ("Live outdoor / TV queue screen", {
                "rxswbd": N, "bissoy": Y, "doctorscare": N, "dpas": N, "medic": N, "proton": Y,
            }),
            ("Voice serial call-out on the screen", {
                "rxswbd": N, "bissoy": Y, "doctorscare": N, "dpas": N, "medic": N, "proton": P,
            }),
            ("Shareable patient ticket (link + WhatsApp)", {
                "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N, "medic": N, "proton": P,
            }),
            ("Live queue position + estimated wait time", {
                "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N, "medic": N, "proton": P,
            }),
            ("\"Doctor is late\" SMS to everyone waiting", {
                "rxswbd": N, "bissoy": P, "doctorscare": N, "dpas": N, "medic": N, "proton": N,
            }),
            ("Call next / patient arrived / in chamber workflow", {
                "rxswbd": P, "bissoy": P, "doctorscare": P, "dpas": P, "medic": P, "proton": Y,
            }),
            ("Call out of turn + reinstate no-show", {
                "rxswbd": N, "bissoy": P, "doctorscare": N, "dpas": N, "medic": N, "proton": P,
            }),
            ("Doctor or staff runs queue (configurable)", {
                "rxswbd": N, "bissoy": N, "doctorscare": P, "dpas": N, "medic": P, "proton": P,
            }),
        ],
    ),
    (
        "Patient communication",
        [
            ("WhatsApp share — ticket, cancel, prescription (free)", {
                "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N, "medic": N, "proton": Y,
            }),
            ("Per-doctor toggles (SMS / WhatsApp per stage)", {
                "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N, "medic": N, "proton": P,
            }),
            ("Patient portal — look up bookings by phone", {
                "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N, "medic": N, "proton": P,
            }),
            ("Booking confirmation SMS (prepaid wallet)", {
                "rxswbd": P, "bissoy": P, "doctorscare": Y, "dpas": P, "medic": P, "proton": Y,
            }),
        ],
    ),
    (
        "Clinical & prescriptions",
        [
            ("Digital prescriptions + print", {
                "rxswbd": Y, "bissoy": N, "doctorscare": Y, "dpas": Y, "medic": Y, "proton": Y,
            }),
            ("Consult screen (clinical context while patient is in)", {
                "rxswbd": N, "bissoy": N, "doctorscare": P, "dpas": N, "medic": N, "proton": P,
            }),
            ("Complete visit, then call next (hand Rx before next patient)", {
                "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N, "medic": N, "proton": P,
            }),
            ("Staff can type paper prescription for doctor", {
                "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N, "medic": N, "proton": N,
            }),
            ("Voice note on visit (private storage)", {
                "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N, "medic": N, "proton": N,
            }),
            ("Photo of paper prescription slip (private)", {
                "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N, "medic": N, "proton": N,
            }),
            ("Short prescription share link (SMS-friendly)", {
                "rxswbd": N, "bissoy": N, "doctorscare": P, "dpas": N, "medic": N, "proton": Y,
            }),
            ("Diagnosis picker (coded + free text)", {
                "rxswbd": P, "bissoy": N, "doctorscare": P, "dpas": P, "medic": P, "proton": P,
            }),
            ("Visit vitals (weight, BP) on record", {
                "rxswbd": N, "bissoy": N, "doctorscare": Y, "dpas": N, "medic": N, "proton": P,
            }),
            ("Medicine list learns doctor's favourites", {
                "rxswbd": P, "bissoy": N, "doctorscare": Y, "dpas": P, "medic": N, "proton": P,
            }),
            ("Catch-up banner for patients missing notes today", {
                "rxswbd": N, "bissoy": N, "doctorscare": N, "dpas": N, "medic": N, "proton": N,
            }),
        ],
    ),
    (
        "Operations & onboarding",
        [
            ("Admin / doctor / staff roles (queue vs clinical split)", {
                "rxswbd": N, "bissoy": N, "doctorscare": Y, "dpas": P, "medic": P, "proton": P,
            }),
            ("Operational day reports (bookings, queue, completion)", {
                "rxswbd": P, "bissoy": P, "doctorscare": P, "dpas": P, "medic": N, "proton": P,
            }),
            ("Done-with-you setup (not self-serve only)", {
                "rxswbd": N, "bissoy": Y, "doctorscare": N, "dpas": Y, "medic": P, "proton": P,
            }),
            ("Multi-doctor clinic tier", {
                "rxswbd": N, "bissoy": N, "doctorscare": P, "dpas": P, "medic": P, "proton": P,
            }),
        ],
    ),
]

PRICING_ROW = [
    (
        "Monthly (solo-style, approx.)",
        "Tk 3,000",
        {
            "rxswbd": "Tk 500",
            "bissoy": "Tk 500–2,000",
            "doctorscare": "Trial",
            "dpas": "Ask",
            "medic": "Tk 500–3,500",
            "proton": "Tk 700",
        },
    ),
    (
        "Setup fee (approx.)",
        "Tk 15,000",
        {
            "rxswbd": "—",
            "bissoy": "Tk 5,000",
            "doctorscare": "—",
            "dpas": "—",
            "medic": "—",
            "proton": "—",
        },
    ),
]
