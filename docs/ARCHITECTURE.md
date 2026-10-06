# TimeInOut — Architecture & Flow Diagrams

Diagrams are written in Mermaid, which GitHub renders automatically. A visual version with screenshots is in [`docs/mockup/index.html`](mockup/index.html).

## 1. System architecture

```mermaid
flowchart LR
  subgraph Users
    P[Parent / Student<br>tablet or phone]
    T[Teacher / Staff]
    A[Subscriber admin<br>laptop]
    O[Platform owner<br>you]
  end

  subgraph DNS["DNS"]
    D1["abc.com"]
    D2["*.abc.com (wildcard)"]
    D3["checkin.school.org<br>(CNAME → abc.com)"]
  end

  subgraph Host["Web hosting (Apache/Nginx + PHP 8)"]
    IDX["public/index.php<br>front controller"]
    RES{"resolve_tenant()<br>path / subdomain / custom domain"}
    PLAT["Platform controller<br>landing · sign-up · /super"]
    KIO["Kiosk + JSON API<br>search · guardians · status · record"]
    ADM["Admin controller<br>dashboard · people · CSV · log · reports · branding"]
    PROV["provision_tenant()"]
    REP["reports.php<br>build + email HTML"]
    CRON["cron/send_reports.php<br>(hourly)"]
  end

  DB[("MySQL<br>tenants · users · students · guardians<br>teachers · materials · attendance · report_log")]
  MAIL["Mail server<br>mail() or SMTP"]
  FS[("uploads/logos")]

  P & T --> D2 & D3
  A --> D2
  O --> D1
  D1 & D2 & D3 --> IDX --> RES
  RES -- "no tenant" --> PLAT
  RES -- "tenant found" --> KIO & ADM
  PLAT --> PROV --> DB
  KIO --> DB
  ADM --> DB
  ADM --> FS
  ADM --> REP
  CRON --> REP --> MAIL
  REP --> DB
```

## 2. Sign-up & automatic provisioning

```mermaid
sequenceDiagram
  actor C as New customer
  participant W as abc.com/signup
  participant API as /check-address
  participant P as provision_tenant()
  participant DB as MySQL
  C->>W: Types "Sunrise Academy"
  W->>API: slug = sunrise-academy
  API-->>W: ✓ available
  C->>W: Name, email, password, plan, time zone, sample data ☑
  W->>P: validated data
  P->>DB: BEGIN
  P->>DB: INSERT tenants (trial, 30 days, branding defaults)
  P->>DB: INSERT users (owner, bcrypt)
  P->>DB: INSERT materials (5 defaults)
  P->>DB: INSERT sample students, guardians, teachers
  P->>DB: COMMIT
  W-->>C: "Your portal is ready" → sunrise-academy.abc.com
  C->>W: Log in → Admin dashboard
```

## 3. Request → tenant resolution

```mermaid
flowchart TD
  R[Incoming request] --> A{"Path starts with /s/slug ?"}
  A -- yes --> A1{slug exists?}
  A1 -- yes --> T[Tenant portal]
  A1 -- no --> N[404 'no portal here']
  A -- no --> B{"Host = abc.com or www?"}
  B -- yes --> PL[Platform site]
  B -- no --> C{"Host = slug.abc.com ?"}
  C -- yes --> A1
  C -- no --> D{"Host = a tenant's custom_domain?"}
  D -- yes --> T
  D -- no --> PL
  T --> S{status suspended / cancelled?}
  S -- yes --> X[403 'portal suspended']
  S -- no --> K[Kiosk / Admin routes]
```

## 4. Kiosk flow (student, teacher, material pickup)

```mermaid
flowchart TD
  S0([Kiosk home]) --> DAY[Pick day: Today / Yesterday / Other]
  DAY --> ROLE{Who are you?}
  ROLE -- Student --> SS[Type a few letters → pick student]
  ROLE -- Teacher/Staff --> TS[Type a few letters → pick teacher]
  ROLE -- Material pickup --> MS[Type a few letters → pick student]

  SS --> G1["Show ONLY this student's guardians<br>(e.g. Father, Mother)"]
  MS --> G2["Show ONLY this student's guardians"]
  G1 --> GP1[Pick guardian]
  G2 --> GP2[Pick guardian]

  GP1 --> ST{Already signed in today?}
  TS --> ST
  ST -- no --> SI[Highlight SIGN IN]
  ST -- yes --> SO[Highlight SIGN OUT<br>'Signed in 8:02 by Sarah']
  SI & SO --> PT{Past day?}
  PT -- yes --> TM[Enter time]
  PT -- no --> REC
  TM --> REC[POST /api/record]

  GP2 --> MAT[Tick items + 'something else']
  MAT --> REC

  REC --> V{Valid?}
  V -- no --> ERR[Show friendly error] --> ROLE
  V -- yes --> OK[✓ Success screen]
  OK -- "6 s or Done" --> S0
```

## 5. Admin workflow

```mermaid
flowchart LR
  L[Login] --> DSH[Dashboard<br>who is on site]
  DSH --> IMP[Import CSV<br>students+parents, teachers]
  DSH --> PEO[Students / Teachers<br>add · edit · deactivate]
  DSH --> MATS[Materials list]
  DSH --> LOG[Attendance log<br>filter · delete · CSV]
  DSH --> RPT[Reports<br>daily · weekly · monthly]
  RPT --> EM[Email now / auto-email settings]
  DSH --> BR[Branding<br>logo · colors · welcome · domain]
  DSH --> USR[Users & passwords]
```

## 6. Scheduled reports

```mermaid
flowchart TD
  C[Cron every hour] --> L[For each trial/active tenant with report emails]
  L --> H{Local time ≥ report_hour?}
  H -- no --> L
  H -- yes --> D["Due: daily (yesterday)<br>+ weekly if Monday (last week)<br>+ monthly if 1st (last month)"]
  D --> R{Already in report_log?}
  R -- yes --> L
  R -- no --> B[report_build → HTML email]
  B --> M[send_mail]
  M -- ok --> W[INSERT report_log] --> L
  M -- failed --> L
```

## 7. Data model (ER)

```mermaid
erDiagram
  TENANTS ||--o{ USERS : has
  TENANTS ||--o{ STUDENTS : has
  TENANTS ||--o{ TEACHERS : has
  TENANTS ||--o{ MATERIALS : has
  TENANTS ||--o{ ATTENDANCE : records
  TENANTS ||--o{ REPORT_LOG : sent
  STUDENTS ||--o{ GUARDIANS : "has (father, mother…)"
  STUDENTS ||--o{ ATTENDANCE : "person (student)"
  TEACHERS ||--o{ ATTENDANCE : "person (teacher)"
  GUARDIANS ||--o{ ATTENDANCE : "drop-off / pick-up by"

  TENANTS { int id PK  string slug UK  string custom_domain UK  string status  string timezone  string primary_color  string logo_path }
  USERS { int id PK  int tenant_id FK  string email  string role }
  STUDENTS { int id PK  int tenant_id FK  string student_code  string first_name  string last_name  string grade }
  GUARDIANS { int id PK  int student_id FK  string name  string relationship  string phone }
  TEACHERS { int id PK  int tenant_id FK  string employee_code  string first_name  string last_name }
  MATERIALS { int id PK  int tenant_id FK  string name }
  ATTENDANCE { int id PK  int tenant_id FK  string person_type  int person_id  string kind  date event_date  time event_time  string guardian_name  string materials }
  REPORT_LOG { int id PK  int tenant_id FK  string period  date period_start }
```
