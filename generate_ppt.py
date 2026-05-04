"""Generate a multi-slide PPT decomposing the 'Functional Team Collaboration' diagram.

The source image is a single dense slide.  This script breaks it apart into
focused slides: title, goal, three pillars (Execution / Core Support / Business),
the seven sub-teams, the central diagram (recreated with shapes), and the
global collaboration network.
"""
from pptx import Presentation
from pptx.util import Inches, Pt, Emu
from pptx.dml.color import RGBColor
from pptx.enum.shapes import MSO_SHAPE
from pptx.enum.text import PP_ALIGN, MSO_ANCHOR
from pptx.oxml.ns import qn
from lxml import etree
import math

# ---------- palette (matches the source slide's blue scheme) -------------
NAVY        = RGBColor(0x0B, 0x2C, 0x5C)
DEEP_BLUE   = RGBColor(0x12, 0x4A, 0x9C)
MID_BLUE    = RGBColor(0x1E, 0x6FB8)  if False else RGBColor(0x1E, 0x6F, 0xB8)
SKY_BLUE    = RGBColor(0x4A, 0x90, 0xD9)
LIGHT_BLUE  = RGBColor(0xBF, 0xD8, 0xEF)
PALE        = RGBColor(0xE9, 0xF1, 0xFA)
WHITE       = RGBColor(0xFF, 0xFF, 0xFF)
DARK_TEXT   = RGBColor(0x1F, 0x2A, 0x44)
GREY_TEXT   = RGBColor(0x55, 0x5F, 0x73)
ACCENT_RED  = RGBColor(0xE0, 0x1E, 0x1E)


def add_textbox(slide, left, top, width, height, text, *,
                size=18, bold=False, color=DARK_TEXT, align=PP_ALIGN.LEFT,
                anchor=MSO_ANCHOR.TOP, font="Calibri"):
    tb = slide.shapes.add_textbox(left, top, width, height)
    tf = tb.text_frame
    tf.word_wrap = True
    tf.vertical_anchor = anchor
    tf.margin_left = tf.margin_right = Inches(0.05)
    tf.margin_top = tf.margin_bottom = Inches(0.02)
    p = tf.paragraphs[0]
    p.alignment = align
    run = p.add_run()
    run.text = text
    run.font.name = font
    run.font.size = Pt(size)
    run.font.bold = bold
    run.font.color.rgb = color
    return tb


def add_rect(slide, left, top, width, height, *,
             fill=DEEP_BLUE, line=None, shape=MSO_SHAPE.RECTANGLE):
    s = slide.shapes.add_shape(shape, left, top, width, height)
    s.fill.solid()
    s.fill.fore_color.rgb = fill
    if line is None:
        s.line.fill.background()
    else:
        s.line.color.rgb = line
        s.line.width = Pt(1)
    s.shadow.inherit = False
    return s


def add_pill(slide, left, top, width, height, text, *,
             fill=DEEP_BLUE, color=WHITE, size=16, bold=True):
    s = add_rect(slide, left, top, width, height,
                 fill=fill, shape=MSO_SHAPE.ROUNDED_RECTANGLE)
    tf = s.text_frame
    tf.margin_left = tf.margin_right = Inches(0.08)
    tf.margin_top = tf.margin_bottom = Inches(0.04)
    tf.vertical_anchor = MSO_ANCHOR.MIDDLE
    p = tf.paragraphs[0]
    p.alignment = PP_ALIGN.CENTER
    run = p.add_run()
    run.text = text
    run.font.name = "Calibri"
    run.font.size = Pt(size)
    run.font.bold = bold
    run.font.color.rgb = color
    return s


def slide_background(slide, color=WHITE):
    bg = slide.shapes.add_shape(
        MSO_SHAPE.RECTANGLE, 0, 0,
        slide.part.package.presentation_part.presentation.slide_width,
        slide.part.package.presentation_part.presentation.slide_height,
    )
    bg.fill.solid()
    bg.fill.fore_color.rgb = color
    bg.line.fill.background()
    bg.shadow.inherit = False
    # send to back
    spTree = bg._element.getparent()
    spTree.remove(bg._element)
    spTree.insert(2, bg._element)
    return bg


def add_top_bar(slide, prs, color=NAVY, height=Inches(0.18)):
    add_rect(slide, 0, 0, prs.slide_width, height, fill=color)


def add_bottom_bar(slide, prs, color=NAVY, height=Inches(0.18)):
    add_rect(slide, 0, prs.slide_height - height, prs.slide_width, height, fill=color)


def add_footer(slide, prs, text="© 2026 Eaton. All rights reserved."):
    add_textbox(slide, Inches(0), prs.slide_height - Inches(0.45),
                prs.slide_width, Inches(0.3),
                text, size=10, color=GREY_TEXT, align=PP_ALIGN.CENTER)


def add_slide_title(slide, prs, title, subtitle=None):
    add_top_bar(slide, prs)
    add_textbox(slide, Inches(0.5), Inches(0.35),
                prs.slide_width - Inches(1.0), Inches(0.7),
                title, size=32, bold=True, color=NAVY)
    if subtitle:
        add_textbox(slide, Inches(0.5), Inches(1.05),
                    prs.slide_width - Inches(1.0), Inches(0.4),
                    subtitle, size=16, color=GREY_TEXT)
    # accent line
    add_rect(slide, Inches(0.5), Inches(1.45),
             Inches(1.2), Inches(0.06), fill=DEEP_BLUE)


# ============================================================
# Build presentation
# ============================================================
prs = Presentation()
prs.slide_width = Inches(13.333)
prs.slide_height = Inches(7.5)

blank = prs.slide_layouts[6]


# ------------- Slide 1: Title -------------------------------------------
s = prs.slides.add_slide(blank)
slide_background(s, NAVY)
# decorative side panel
add_rect(s, 0, 0, Inches(4.2), prs.slide_height, fill=DEEP_BLUE)
add_rect(s, Inches(4.2), 0, Inches(0.08), prs.slide_height, fill=SKY_BLUE)

add_textbox(s, Inches(0.6), Inches(0.6), Inches(3.4), Inches(0.5),
            "EATON × TSMC", size=18, bold=True, color=LIGHT_BLUE)
add_textbox(s, Inches(0.6), Inches(2.6), Inches(3.4), Inches(0.5),
            "2026", size=14, color=LIGHT_BLUE)

add_textbox(s, Inches(4.8), Inches(2.2), Inches(8.0), Inches(1.2),
            "Functional Team", size=44, bold=True, color=WHITE)
add_textbox(s, Inches(4.8), Inches(3.0), Inches(8.0), Inches(1.2),
            "Collaboration", size=44, bold=True, color=SKY_BLUE)
add_rect(s, Inches(4.8), Inches(4.05), Inches(1.5), Inches(0.06), fill=SKY_BLUE)
add_textbox(s, Inches(4.8), Inches(4.2), Inches(8.0), Inches(0.5),
            "One Goal — Delivering Excellence for TSMC",
            size=20, color=LIGHT_BLUE)

add_textbox(s, Inches(0.6), prs.slide_height - Inches(0.55),
            Inches(6), Inches(0.3),
            "© 2026 Eaton. All rights reserved.",
            size=10, color=LIGHT_BLUE)


# ------------- Slide 2: Goal & Agenda -----------------------------------
s = prs.slides.add_slide(blank)
slide_background(s, WHITE)
add_slide_title(s, prs, "Our Shared Goal",
                "Aligning every function around a single customer outcome")

# Big quote-style goal block
goal_box = add_rect(s, Inches(0.7), Inches(1.9),
                    Inches(11.9), Inches(1.4), fill=PALE)
tf = goal_box.text_frame
tf.margin_left = tf.margin_right = Inches(0.3)
tf.vertical_anchor = MSO_ANCHOR.MIDDLE
p = tf.paragraphs[0]
p.alignment = PP_ALIGN.CENTER
r = p.add_run()
r.text = "Delivering Excellence for TSMC"
r.font.size = Pt(32); r.font.bold = True; r.font.color.rgb = NAVY
p2 = tf.add_paragraph()
p2.alignment = PP_ALIGN.CENTER
r2 = p2.add_run()
r2.text = "through cross-functional collaboration, global reach, and customer-centric execution"
r2.font.size = Pt(16); r2.font.color.rgb = GREY_TEXT

# 3 pillar pills
pills = [
    ("EXECUTION",    "Plan & Manage · Deliver & Execute", DEEP_BLUE),
    ("CORE SUPPORT", "Enable & Support",                  MID_BLUE),
    ("BUSINESS",     "Connect & Serve · Create Value",    SKY_BLUE),
]
for i, (label, sub, color) in enumerate(pills):
    left = Inches(0.7 + i * 4.1)
    add_pill(s, left, Inches(3.7), Inches(3.9), Inches(0.7),
             label, fill=color, size=20)
    add_textbox(s, left, Inches(4.5), Inches(3.9), Inches(0.5),
                sub, size=13, color=GREY_TEXT, align=PP_ALIGN.CENTER)

# agenda
add_textbox(s, Inches(0.7), Inches(5.4), Inches(12), Inches(0.4),
            "Agenda", size=16, bold=True, color=NAVY)
add_rect(s, Inches(0.7), Inches(5.78), Inches(0.8), Inches(0.04), fill=DEEP_BLUE)
agenda_items = [
    "1.  Three Pillars of Collaboration",
    "2.  Project Core Team & Surrounding Functions",
    "3.  Global Collaboration Network",
    "4.  Operating Principles",
]
for i, t in enumerate(agenda_items):
    add_textbox(s, Inches(0.7 + (i % 2) * 6.0),
                Inches(5.95 + (i // 2) * 0.4),
                Inches(6), Inches(0.35),
                t, size=14, color=DARK_TEXT)

add_footer(s, prs)


# ------------- Slide 3: Three Pillars overview --------------------------
s = prs.slides.add_slide(blank)
slide_background(s, WHITE)
add_slide_title(s, prs, "Three Pillars of Collaboration",
                "How every team contributes to the shared goal")

cols = [
    {
        "pill": "EXECUTION",
        "tagline": "Plan & Manage  ·  Deliver & Execute",
        "color": DEEP_BLUE,
        "items": [
            ("Project Management Team", "End-to-end planning, scheduling, risk control"),
            ("Factory Unit Team",       "Production readiness and on-site delivery"),
        ],
    },
    {
        "pill": "CORE SUPPORT",
        "tagline": "Enable & Support",
        "color": MID_BLUE,
        "items": [
            ("APAC R&D Service Team",  "Engineering design and product enablement"),
            ("Technical Support Team", "On-call expertise and troubleshooting"),
            ("SCM Team",               "Supply continuity and material readiness"),
        ],
    },
    {
        "pill": "BUSINESS",
        "tagline": "Connect & Serve  ·  Create Customer Value",
        "color": SKY_BLUE,
        "items": [
            ("Customer Service Team", "Single point of contact for TSMC"),
            ("Sales Team",            "Account growth and value creation"),
        ],
    },
]

col_w = Inches(4.1)
gap   = Inches(0.15)
left0 = Inches(0.55)
top0  = Inches(1.85)

for i, c in enumerate(cols):
    left = left0 + (col_w + gap) * i
    # header pill
    add_pill(s, left, top0, col_w, Inches(0.55),
             c["pill"], fill=c["color"], size=18)
    add_textbox(s, left, top0 + Inches(0.6), col_w, Inches(0.4),
                c["tagline"], size=12, color=GREY_TEXT,
                align=PP_ALIGN.CENTER)
    # body card
    card_top = top0 + Inches(1.05)
    card_h   = Inches(4.6)
    card = add_rect(s, left, card_top, col_w, card_h, fill=PALE)
    # items
    for j, (name, desc) in enumerate(c["items"]):
        item_top = card_top + Inches(0.25 + j * 1.45)
        add_textbox(s, left + Inches(0.25), item_top,
                    col_w - Inches(0.5), Inches(0.4),
                    name, size=14, bold=True, color=c["color"])
        add_textbox(s, left + Inches(0.25), item_top + Inches(0.4),
                    col_w - Inches(0.5), Inches(0.9),
                    desc, size=11, color=DARK_TEXT)
        if j < len(c["items"]) - 1:
            add_rect(s, left + Inches(0.25),
                     item_top + Inches(1.25),
                     col_w - Inches(0.5), Emu(9000),
                     fill=LIGHT_BLUE)

add_footer(s, prs)


# ------------- Slide 4: Project Core Team diagram -----------------------
s = prs.slides.add_slide(blank)
slide_background(s, WHITE)
add_slide_title(s, prs, "TSMC Project Core Team",
                "Seven functions orbiting one customer mission")

# center
cx = prs.slide_width / 2
cy = Inches(4.4)
R_outer = Inches(2.4)
R_inner = Inches(1.1)

# outer ring (decorative)
ring = s.shapes.add_shape(MSO_SHAPE.OVAL,
                          cx - R_outer, cy - R_outer,
                          R_outer * 2, R_outer * 2)
ring.fill.solid(); ring.fill.fore_color.rgb = LIGHT_BLUE
ring.line.fill.background(); ring.shadow.inherit = False

# inner core
core = s.shapes.add_shape(MSO_SHAPE.OVAL,
                          cx - R_inner, cy - R_inner,
                          R_inner * 2, R_inner * 2)
core.fill.solid(); core.fill.fore_color.rgb = DEEP_BLUE
core.line.color.rgb = WHITE; core.line.width = Pt(3)
core.shadow.inherit = False
tf = core.text_frame
tf.vertical_anchor = MSO_ANCHOR.MIDDLE
p = tf.paragraphs[0]; p.alignment = PP_ALIGN.CENTER
r = p.add_run(); r.text = "TSMC"
r.font.size = Pt(20); r.font.bold = True; r.font.color.rgb = WHITE
p2 = tf.add_paragraph(); p2.alignment = PP_ALIGN.CENTER
r2 = p2.add_run(); r2.text = "Project Core Team"
r2.font.size = Pt(12); r2.font.color.rgb = LIGHT_BLUE

# 7 surrounding teams placed around the ring
teams = [
    "Project\nManagement Team",
    "APAC R&D\nService Team",
    "Technical\nSupport Team",
    "Customer\nService Team",
    "Sales\nTeam",
    "Factory\nUnit Team",
    "SCM\nTeam",
]
N = len(teams)
node_w = Inches(1.7); node_h = Inches(0.85)
ring_r = Inches(2.95)
for i, label in enumerate(teams):
    angle = -math.pi / 2 + 2 * math.pi * i / N
    x = cx + ring_r * math.cos(angle) - node_w / 2
    y = cy + ring_r * math.sin(angle) - node_h / 2
    fill = MID_BLUE if i % 2 == 0 else SKY_BLUE
    node = add_rect(s, int(x), int(y), node_w, node_h,
                    fill=fill, shape=MSO_SHAPE.ROUNDED_RECTANGLE)
    tf = node.text_frame
    tf.margin_left = tf.margin_right = Inches(0.05)
    tf.vertical_anchor = MSO_ANCHOR.MIDDLE
    tf.word_wrap = True
    for k, line in enumerate(label.split("\n")):
        p = tf.paragraphs[0] if k == 0 else tf.add_paragraph()
        p.alignment = PP_ALIGN.CENTER
        rr = p.add_run(); rr.text = line
        rr.font.size = Pt(11); rr.font.bold = True; rr.font.color.rgb = WHITE

# left/right pillar callouts
add_pill(s, Inches(0.4), Inches(3.3), Inches(2.0), Inches(0.55),
         "EXECUTION", fill=NAVY, size=14)
add_textbox(s, Inches(0.4), Inches(3.9), Inches(2.0), Inches(0.7),
            "Plan & Manage\nDeliver & Execute",
            size=11, color=GREY_TEXT, align=PP_ALIGN.CENTER)

add_pill(s, prs.slide_width - Inches(2.4), Inches(3.3),
         Inches(2.0), Inches(0.55),
         "BUSINESS", fill=SKY_BLUE, size=14)
add_textbox(s, prs.slide_width - Inches(2.4), Inches(3.9),
            Inches(2.0), Inches(0.7),
            "Connect & Serve\nCreate Customer Value",
            size=11, color=GREY_TEXT, align=PP_ALIGN.CENTER)

# top label
add_pill(s, cx - Inches(1.1), Inches(1.75), Inches(2.2), Inches(0.5),
         "CORE SUPPORT", fill=DEEP_BLUE, size=14)
add_textbox(s, cx - Inches(1.5), Inches(2.25), Inches(3.0), Inches(0.4),
            "Enable & Support", size=11, color=GREY_TEXT,
            align=PP_ALIGN.CENTER)

add_footer(s, prs)


# ------------- Slide 5: Execution detail --------------------------------
def detail_slide(title_text, subtitle, color, teams_data):
    s = prs.slides.add_slide(blank)
    slide_background(s, WHITE)
    add_slide_title(s, prs, title_text, subtitle)
    # accent strip
    add_rect(s, 0, Inches(1.45), prs.slide_width, Inches(0.06), fill=color)

    n = len(teams_data)
    card_w = Inches(min(5.0, 11.5 / n))
    total_w = card_w * n + Inches(0.3) * (n - 1)
    left0 = (prs.slide_width - total_w) / 2
    for i, (name, role, bullets) in enumerate(teams_data):
        left = left0 + (card_w + Inches(0.3)) * i
        top  = Inches(1.95)
        h    = Inches(4.9)
        # header
        add_rect(s, left, top, card_w, Inches(0.7), fill=color)
        add_textbox(s, left, top + Inches(0.1), card_w, Inches(0.5),
                    name, size=18, bold=True, color=WHITE,
                    align=PP_ALIGN.CENTER)
        # body
        body_top = top + Inches(0.7)
        body_h   = h - Inches(0.7)
        add_rect(s, left, body_top, card_w, body_h, fill=PALE)
        add_textbox(s, left + Inches(0.25), body_top + Inches(0.2),
                    card_w - Inches(0.5), Inches(0.5),
                    role, size=13, bold=True, color=color)
        # bullets
        tb = s.shapes.add_textbox(left + Inches(0.25),
                                  body_top + Inches(0.75),
                                  card_w - Inches(0.5),
                                  body_h - Inches(0.9))
        tf = tb.text_frame; tf.word_wrap = True
        for j, b in enumerate(bullets):
            p = tf.paragraphs[0] if j == 0 else tf.add_paragraph()
            p.alignment = PP_ALIGN.LEFT
            p.space_after = Pt(6)
            r = p.add_run(); r.text = "•  " + b
            r.font.size = Pt(12); r.font.color.rgb = DARK_TEXT
    add_footer(s, prs)


detail_slide(
    "Execution",
    "Plan & Manage · Deliver & Execute",
    DEEP_BLUE,
    [
        ("Project Management Team",
         "Plan & Manage",
         ["Owns master schedule and milestones",
          "Cross-functional coordination & RACI",
          "Risk, change and issue management",
          "Single source of truth for status"]),
        ("Factory Unit Team",
         "Deliver & Execute",
         ["Production readiness and capacity",
          "Quality control and yield assurance",
          "On-time delivery to TSMC sites",
          "Continuous improvement on the floor"]),
    ],
)


# ------------- Slide 6: Core Support detail -----------------------------
detail_slide(
    "Core Support",
    "Enable & Support — the engine behind every commitment",
    MID_BLUE,
    [
        ("APAC R&D Service Team",
         "Engineering Enablement",
         ["Localized design & customization",
          "Application engineering for TSMC",
          "Roadmap alignment with customer needs"]),
        ("Technical Support Team",
         "Expert Response",
         ["24×7 escalation and troubleshooting",
          "Field service & commissioning",
          "Knowledge base and training"]),
        ("SCM Team",
         "Supply Continuity",
         ["Material planning and forecasting",
          "Supplier quality and sourcing",
          "Logistics and on-time fulfillment"]),
    ],
)


# ------------- Slide 7: Business detail ---------------------------------
detail_slide(
    "Business",
    "Connect & Serve · Create Customer Value",
    SKY_BLUE,
    [
        ("Customer Service Team",
         "Connect & Serve",
         ["Primary interface with TSMC",
          "Order management and case tracking",
          "Voice-of-customer feedback loop",
          "Service-level and satisfaction owner"]),
        ("Sales Team",
         "Create Customer Value",
         ["Account strategy and growth plans",
          "Commercial negotiation and contracts",
          "Solution positioning across BUs",
          "Long-term partnership development"]),
    ],
)


# ------------- Slide 8: Global Collaboration Network --------------------
s = prs.slides.add_slide(blank)
slide_background(s, WHITE)
add_slide_title(s, prs, "Global Collaboration Network",
                "Five regions, one team for TSMC")

regions = [
    ("Taiwan",  "(HQ)",            "Project Leadership\n& Coordination",  RGBColor(0xC8, 0x10, 0x2E)),
    ("Japan",   "(Site)",          "Advanced Support\n& Service",         RGBColor(0xBC, 0x00, 0x2D)),
    ("Germany", "(Engineering)",   "Engineering Excellence\n& Innovation", RGBColor(0xFF, 0xCE, 0x00)),
    ("USA",     "(Support)",       "Technical Support\n& Solutions",      RGBColor(0x3C, 0x3B, 0x6E)),
    ("China",   "(Manufacturing)", "Manufacturing Support\n& Execution",  RGBColor(0xDE, 0x29, 0x10)),
]

n = len(regions)
card_w = Inches(2.25)
gap    = Inches(0.2)
total  = card_w * n + gap * (n - 1)
left0  = (prs.slide_width - total) / 2
top    = Inches(2.1)

for i, (country, role, desc, accent) in enumerate(regions):
    left = left0 + (card_w + gap) * i
    # accent bar at top of each card
    add_rect(s, left, top, card_w, Inches(0.18), fill=accent)
    # main card
    card = add_rect(s, left, top + Inches(0.18),
                    card_w, Inches(3.6), fill=PALE)
    # circle with country code (instead of an actual flag)
    cd_r = Inches(0.55)
    circle_left = left + (card_w - cd_r * 2) / 2
    circle_top  = top + Inches(0.5)
    circle = s.shapes.add_shape(MSO_SHAPE.OVAL,
                                circle_left, circle_top,
                                cd_r * 2, cd_r * 2)
    circle.fill.solid(); circle.fill.fore_color.rgb = accent
    circle.line.color.rgb = WHITE; circle.line.width = Pt(2)
    circle.shadow.inherit = False
    tf = circle.text_frame
    tf.vertical_anchor = MSO_ANCHOR.MIDDLE
    p = tf.paragraphs[0]; p.alignment = PP_ALIGN.CENTER
    r = p.add_run()
    code = {"Taiwan": "TW", "Japan": "JP", "Germany": "DE",
            "USA": "US", "China": "CN"}[country]
    r.text = code
    r.font.size = Pt(18); r.font.bold = True; r.font.color.rgb = WHITE
    # country name
    add_textbox(s, left, top + Inches(1.85),
                card_w, Inches(0.45),
                country, size=18, bold=True, color=NAVY,
                align=PP_ALIGN.CENTER)
    add_textbox(s, left, top + Inches(2.25),
                card_w, Inches(0.35),
                role, size=12, color=GREY_TEXT, align=PP_ALIGN.CENTER)
    # divider
    add_rect(s, left + Inches(0.6), top + Inches(2.65),
             card_w - Inches(1.2), Emu(9000), fill=LIGHT_BLUE)
    # description
    add_textbox(s, left + Inches(0.15), top + Inches(2.8),
                card_w - Inches(0.3), Inches(1.0),
                desc, size=12, color=DARK_TEXT, align=PP_ALIGN.CENTER)

add_footer(s, prs)


# ------------- Slide 9: Operating Principles / Closing ------------------
s = prs.slides.add_slide(blank)
slide_background(s, WHITE)
add_slide_title(s, prs, "How We Operate",
                "Principles that turn structure into outcomes")

principles = [
    ("Customer First",       "Every decision starts with TSMC's needs and outcomes."),
    ("One Team",             "Functions act as one organization across regions."),
    ("Clear Ownership",      "Defined roles via Execution / Core Support / Business."),
    ("Speed with Quality",   "Fast response, never at the expense of reliability."),
    ("Transparent Comms",    "Single source of truth for status, risks and changes."),
    ("Continuous Learning",  "Lessons captured and shared across the network."),
]

cols = 3; rows = 2
card_w = Inches(4.0); card_h = Inches(2.1)
gap_x = Inches(0.2); gap_y = Inches(0.25)
total_w = card_w * cols + gap_x * (cols - 1)
left0 = (prs.slide_width - total_w) / 2
top0 = Inches(1.95)

for i, (title, desc) in enumerate(principles):
    r = i // cols; c = i % cols
    left = left0 + (card_w + gap_x) * c
    top  = top0 + (card_h + gap_y) * r
    # left accent
    add_rect(s, left, top, Inches(0.12), card_h, fill=DEEP_BLUE)
    add_rect(s, left + Inches(0.12), top,
             card_w - Inches(0.12), card_h, fill=PALE)
    add_textbox(s, left + Inches(0.35), top + Inches(0.2),
                card_w - Inches(0.5), Inches(0.5),
                title, size=18, bold=True, color=NAVY)
    add_textbox(s, left + Inches(0.35), top + Inches(0.8),
                card_w - Inches(0.5), card_h - Inches(0.9),
                desc, size=13, color=DARK_TEXT)

add_footer(s, prs)


# ------------- Slide 10: Closing ----------------------------------------
s = prs.slides.add_slide(blank)
slide_background(s, NAVY)
add_rect(s, 0, Inches(3.6), prs.slide_width, Inches(0.06), fill=SKY_BLUE)

add_textbox(s, Inches(0.5), Inches(2.4), prs.slide_width - Inches(1),
            Inches(1.0),
            "One Team. One Goal.",
            size=44, bold=True, color=WHITE, align=PP_ALIGN.CENTER)
add_textbox(s, Inches(0.5), Inches(3.8), prs.slide_width - Inches(1),
            Inches(0.7),
            "Delivering Excellence for TSMC",
            size=24, color=SKY_BLUE, align=PP_ALIGN.CENTER)
add_textbox(s, Inches(0.5), Inches(4.6), prs.slide_width - Inches(1),
            Inches(0.5),
            "Thank You",
            size=18, color=LIGHT_BLUE, align=PP_ALIGN.CENTER)
add_textbox(s, 0, prs.slide_height - Inches(0.5),
            prs.slide_width, Inches(0.3),
            "© 2026 Eaton. All rights reserved.",
            size=10, color=LIGHT_BLUE, align=PP_ALIGN.CENTER)


# ------------- Slide 11: Single-page recreation of the source image -----
s = prs.slides.add_slide(blank)
slide_background(s, WHITE)

# Top + bottom navy bars (matches the source slide framing)
add_rect(s, 0, 0, prs.slide_width, Inches(0.18), fill=NAVY)
add_rect(s, 0, prs.slide_height - Inches(0.18),
         prs.slide_width, Inches(0.18), fill=NAVY)

# Title block (with thin underline like the source)
add_textbox(s, Inches(0.4), Inches(0.3),
            Inches(8.0), Inches(0.6),
            "Functional Team Collaboration",
            size=30, bold=True, color=NAVY)
add_rect(s, Inches(0.4), Inches(0.92), Inches(7.5), Emu(15000), fill=NAVY)
add_textbox(s, Inches(0.4), Inches(0.95),
            Inches(8.0), Inches(0.4),
            "One Goal – Delivering Excellence for TSMC",
            size=14, color=DARK_TEXT)

# ----- Center diagram geometry -----
cx = prs.slide_width / 2
cy = Inches(4.0)
R_outer = Inches(2.05)
R_inner = Inches(0.95)

# Outer light blue ring
ring = s.shapes.add_shape(MSO_SHAPE.OVAL,
                          int(cx - R_outer), int(cy - R_outer),
                          int(R_outer * 2), int(R_outer * 2))
ring.fill.solid(); ring.fill.fore_color.rgb = LIGHT_BLUE
ring.line.color.rgb = WHITE; ring.line.width = Pt(2)
ring.shadow.inherit = False

# Pie-style ring segments (approximated with PIE shapes for a 3-tone ring)
# Three quadrant blocks of color across the ring (deep / mid / sky)
for start, end, color in [(-90, 30, DEEP_BLUE),
                          (30, 150, SKY_BLUE),
                          (150, 270, MID_BLUE)]:
    pie = s.shapes.add_shape(MSO_SHAPE.PIE,
                             int(cx - R_outer), int(cy - R_outer),
                             int(R_outer * 2), int(R_outer * 2))
    pie.fill.solid(); pie.fill.fore_color.rgb = color
    pie.line.fill.background(); pie.shadow.inherit = False
    # set adj1/adj2 angles via XML (PIE adj values are in 60000ths of a degree,
    # measured clockwise from 3 o'clock; python-pptx exposes adjustments)
    pie.adjustments[0] = start
    pie.adjustments[1] = end

# White inner disc to make the colored ring hollow
hole = s.shapes.add_shape(MSO_SHAPE.OVAL,
                          int(cx - R_inner - Inches(0.15)),
                          int(cy - R_inner - Inches(0.15)),
                          int((R_inner + Inches(0.15)) * 2),
                          int((R_inner + Inches(0.15)) * 2))
hole.fill.solid(); hole.fill.fore_color.rgb = WHITE
hole.line.fill.background(); hole.shadow.inherit = False

# Center core (TSMC)
core = s.shapes.add_shape(MSO_SHAPE.OVAL,
                          int(cx - R_inner), int(cy - R_inner),
                          int(R_inner * 2), int(R_inner * 2))
core.fill.solid(); core.fill.fore_color.rgb = WHITE
core.line.color.rgb = DEEP_BLUE; core.line.width = Pt(2)
core.shadow.inherit = False
tf = core.text_frame
tf.vertical_anchor = MSO_ANCHOR.MIDDLE
tf.margin_top = tf.margin_bottom = Inches(0.05)
p = tf.paragraphs[0]; p.alignment = PP_ALIGN.CENTER
r = p.add_run(); r.text = "tsmc"
r.font.size = Pt(14); r.font.bold = True; r.font.color.rgb = ACCENT_RED
p2 = tf.add_paragraph(); p2.alignment = PP_ALIGN.CENTER
r2 = p2.add_run(); r2.text = "TSMC"
r2.font.size = Pt(16); r2.font.bold = True; r2.font.color.rgb = NAVY
p3 = tf.add_paragraph(); p3.alignment = PP_ALIGN.CENTER
r3 = p3.add_run(); r3.text = "Project"
r3.font.size = Pt(11); r3.font.color.rgb = NAVY
p4 = tf.add_paragraph(); p4.alignment = PP_ALIGN.CENTER
r4 = p4.add_run(); r4.text = "Core Team"
r4.font.size = Pt(11); r4.font.bold = True; r4.font.color.rgb = NAVY

# Seven team labels around the ring (matches the source ordering)
# Angles measured from 12 o'clock, clockwise
team_specs = [
    ( -55, "APAC R&D\nService Team",   DEEP_BLUE),
    (  -5, "Technical\nSupport Team",  SKY_BLUE),
    (  55, "Customer\nService\nTeam",  SKY_BLUE),
    ( 110, "Sales\nTeam",              MID_BLUE),
    ( 175, "SCM Team",                 MID_BLUE),
    (-125, "Factory\nUnit Team",       MID_BLUE),
    (-175, "Project\nManagement\nTeam",DEEP_BLUE),
]
label_r = Inches(2.55)
for deg, text, color in team_specs:
    rad = math.radians(deg - 90)  # convert to math angle
    lx = cx + label_r * math.cos(rad)
    ly = cy + label_r * math.sin(rad)
    box_w = Inches(1.4); box_h = Inches(0.7)
    tb = s.shapes.add_textbox(int(lx - box_w / 2),
                              int(ly - box_h / 2),
                              box_w, box_h)
    tf = tb.text_frame; tf.word_wrap = True
    tf.vertical_anchor = MSO_ANCHOR.MIDDLE
    for j, line in enumerate(text.split("\n")):
        p = tf.paragraphs[0] if j == 0 else tf.add_paragraph()
        p.alignment = PP_ALIGN.CENTER
        rr = p.add_run(); rr.text = line
        rr.font.size = Pt(10); rr.font.bold = True
        rr.font.color.rgb = color

# Top "CORE SUPPORT" pill + tagline
add_pill(s, int(cx - Inches(1.1)), Inches(1.5),
         Inches(2.2), Inches(0.4),
         "CORE SUPPORT", fill=NAVY, size=12)
add_textbox(s, int(cx - Inches(1.5)), Inches(1.92),
            Inches(3.0), Inches(0.3),
            "Enable & Support", size=11, bold=True,
            color=DARK_TEXT, align=PP_ALIGN.CENTER)

# Left "EXECUTION" pill + tagline + connector
add_pill(s, Inches(0.3), Inches(3.7),
         Inches(1.7), Inches(0.45),
         "EXECUTION", fill=NAVY, size=12)
add_textbox(s, Inches(0.2), Inches(4.18),
            Inches(1.9), Inches(0.65),
            "Plan & Manage\nDeliver & Execute",
            size=10, color=GREY_TEXT, align=PP_ALIGN.CENTER)
# dotted connector to ring (approximated as a thin rectangle)
add_rect(s, Inches(2.0), Inches(3.92),
         int(cx - R_outer - Inches(2.0)), Emu(9000),
         fill=DEEP_BLUE)
# small dot at ring edge
dot1 = s.shapes.add_shape(MSO_SHAPE.OVAL,
                          int(cx - R_outer - Inches(0.07)),
                          Inches(3.86),
                          Inches(0.14), Inches(0.14))
dot1.fill.solid(); dot1.fill.fore_color.rgb = DEEP_BLUE
dot1.line.fill.background(); dot1.shadow.inherit = False

# Right "BUSINESS" pill + tagline + connector
add_pill(s, prs.slide_width - Inches(2.0), Inches(3.7),
         Inches(1.7), Inches(0.45),
         "BUSINESS", fill=SKY_BLUE, size=12)
add_textbox(s, prs.slide_width - Inches(2.1), Inches(4.18),
            Inches(1.9), Inches(0.65),
            "Connect & Serve\nCreate Customer Value",
            size=10, color=GREY_TEXT, align=PP_ALIGN.CENTER)
add_rect(s, int(cx + R_outer), Inches(3.92),
         int(prs.slide_width - Inches(2.0) - cx - R_outer),
         Emu(9000), fill=SKY_BLUE)
dot2 = s.shapes.add_shape(MSO_SHAPE.OVAL,
                          int(cx + R_outer - Inches(0.07)),
                          Inches(3.86),
                          Inches(0.14), Inches(0.14))
dot2.fill.solid(); dot2.fill.fore_color.rgb = SKY_BLUE
dot2.line.fill.background(); dot2.shadow.inherit = False

# ---------- Bottom Global Collaboration Network bar ----------
bar_top = Inches(6.1)
bar_h   = Inches(1.15)
bar_left = Inches(0.4)
bar_right = prs.slide_width - Inches(0.4)
bar_w = bar_right - bar_left
bar = add_rect(s, bar_left, bar_top, bar_w, bar_h, fill=PALE)
bar.line.color.rgb = LIGHT_BLUE; bar.line.width = Pt(0.75)

add_textbox(s, bar_left, bar_top + Inches(0.05),
            bar_w, Inches(0.3),
            "Global Collaboration Network",
            size=13, bold=True, color=NAVY, align=PP_ALIGN.CENTER)

regions2 = [
    ("Taiwan (HQ)",            "Project Leadership\n& Coordination",   "TW", RGBColor(0xC8, 0x10, 0x2E)),
    ("Japan (Site)",           "Advanced Support\n& Service",          "JP", RGBColor(0xBC, 0x00, 0x2D)),
    ("Germany (Engineering)",  "Engineering Excellence\n& Innovation", "DE", RGBColor(0xFF, 0xCE, 0x00)),
    ("USA (Support)",          "Technical Support\n& Solutions",       "US", RGBColor(0x3C, 0x3B, 0x6E)),
    ("China (Manufacturing)",  "Manufacturing Support\n& Execution",   "CN", RGBColor(0xDE, 0x29, 0x10)),
]
n2 = len(regions2)
cell_w = bar_w / n2
for i, (name, desc, code, color) in enumerate(regions2):
    cl = bar_left + cell_w * i
    # small flag-like circle
    cd = Inches(0.32)
    circle = s.shapes.add_shape(MSO_SHAPE.OVAL,
                                int(cl + cell_w / 2 - cd / 2),
                                bar_top + Inches(0.32),
                                cd, cd)
    circle.fill.solid(); circle.fill.fore_color.rgb = color
    circle.line.color.rgb = WHITE; circle.line.width = Pt(1)
    circle.shadow.inherit = False
    tf = circle.text_frame
    tf.vertical_anchor = MSO_ANCHOR.MIDDLE
    tf.margin_left = tf.margin_right = Inches(0.0)
    tf.margin_top = tf.margin_bottom = Inches(0.0)
    p = tf.paragraphs[0]; p.alignment = PP_ALIGN.CENTER
    rr = p.add_run(); rr.text = code
    rr.font.size = Pt(9); rr.font.bold = True; rr.font.color.rgb = WHITE
    # name
    add_textbox(s, cl, bar_top + Inches(0.7),
                cell_w, Inches(0.22),
                name, size=10, bold=True, color=NAVY,
                align=PP_ALIGN.CENTER)
    # role
    add_textbox(s, cl + Inches(0.05), bar_top + Inches(0.9),
                cell_w - Inches(0.1), Inches(0.4),
                desc, size=8, color=GREY_TEXT, align=PP_ALIGN.CENTER)

# Footer copyright (above the bottom navy bar)
add_textbox(s, 0, prs.slide_height - Inches(0.4),
            prs.slide_width, Inches(0.2),
            "© 2026 Eaton. All rights reserved.",
            size=9, color=GREY_TEXT, align=PP_ALIGN.CENTER)


# ------------- Save -----------------------------------------------------
out = "Functional_Team_Collaboration.pptx"
prs.save(out)
print(f"Wrote {out} with {len(prs.slides)} slides.")
