# Specification implementation handover

The existing PHP/MySQL project has been extended using spec.txt and the supplied SUPERKING HTML/Markdown concept. This describes local implementation, rather than a claim that business content or live hosting has been approved.

| Brief area | Implemented |
| --- | --- |
| Brand design and homepage | Supplied palette/logo; responsive yellow hero, alternating sections, categories, manufacturing, knowledge, dealer/distributor and support links |
| Company background | Royal Myanmar King Company Limited, UAE imports/distribution in Myanmar since 2016, preparation for local manufacturing |
| Products | Staff CMS, English/Myanmar content, genuine image upload, documented code/SAE/specification/application/packages/storage, TDS/SDS, search and combined filters |
| Manufacturing/quality | Verified progress records/photos; current stage reflects the latest update; approved certificates/technical PDFs |
| Knowledge/news | Separate listings, seven knowledge topics, reviewed technical articles, metadata, image alt text and related-product links |
| Dealers | Staff-managed verified listings, region/city/township search, phone/address and optional Google Maps links |
| Distributor/export | Company/country/business type, category interest, volume, distribution network and message fields; approved profile/catalogue downloads |
| Support/authenticity | Product/complaint/general forms, batch-number field, server validation, CSRF, honeypot, limits, stored inquiries and reference confirmation |
| Administration | Live overview/inquiry KPIs, all CMS CRUD, publication/type/verification filters, sorting, saved list context, authenticated product/article draft previews, inquiry notes/date filters/CSV export, activity history, account updates and responsive theme/density modes |
| Languages/SEO | English/Myanmar URLs and alternates, metadata, structured data, sitemap, robots, Search Console token and optional consent-based GA4 |
| Handover/security | Source, staff steps, verification suite, backup/restore and launch notes in README |

## Material needed from the company

- Complete official Brand Guideline and licensed web fonts. Chakra Petch and Noto Sans Myanmar are font-stack preferences with system fallbacks; no external font CDN or unlicensed font files are installed.
- Confirmation of the supplied logo's “SINCE 2015” versus the brief's Myanmar-distribution date of 2016. The logo is preserved; written copy says distribution in Myanmar since 2016 rather than claiming a global founding year.
- Genuine product photos, official names/codes, documented specifications, supported claims, packages, applications and approved TDS/SDS.
- Verified factory/equipment photographs, actual project milestones, procedures, laboratory information and certificates. No stock photograph is presented as a SUPERKING facility.
- Technically reviewed knowledge articles and approved announcements. Basic support FAQs and topic navigation are provided initially.
- Authorized dealer records and confirmed company contact channels. Starter example contacts have been moved to draft.
- Approved company profile and catalogue PDFs. Downloads appear when uploaded, verified and published.
- Review of Myanmar UI translations and approved translations of staff-managed content.
- Approved privacy wording, retention schedule and staff responsibility for responding to inquiries.

## Launch services and access

The work is available locally at http://localhost/superking-website/. Domain purchase, external hosting, HTTPS certificate installation and production deployment have not been performed. The company must supply hosting/domain access and choose production arrangements.

GA4 and Search Console are ready for company IDs/tokens in Brand & settings. Account creation, property verification and access grants require company account access. No analytics requests run until a valid ID is configured and a visitor consents.

Inquiries are saved in the CMS; outgoing email notifications are not configured. Staff must monitor the inbox. Notifications require an approved mail delivery service and recipients.

Batch/QR verification is explicitly described as unavailable. A future system needs issued per-unit codes or registered batch records, a company-controlled source of truth, revocation/counterfeit rules and tested customer results before activation. No simulated verification result is shown.

Actual staff training, hosting charges, maintenance agreements, warranty terms and a commercial quotation require business arrangements. README supplies the operational walkthrough; no training session or external account handover has been claimed.

## Content and SEO plan

1. Publish approved contacts, genuine product records/PDFs and verified dealer locations first.
2. Add reviewed fundamentals and vehicle-specific selection articles, each linking to documented relevant products.
3. Add dated manufacturing milestones and packaging/distribution announcements as verified information becomes available.
4. Review Myanmar translations, metadata and search terms with the company; keyword research for target export markets remains prelaunch work.
5. Measure production performance and indexability, then use Search Console evidence to improve relevant content. Do not invent approvals, testimonials or performance claims.

## Technical verification

The isolated suite checks migrations, public routes, English/Myanmar URLs, technical filters, publication checks, sanitization, approved PDF/image handling, distributor and complaint inquiries, inbox actions, spam limits, sitemap/draft exclusion, mobile layouts, menu/language controls and admin theme/density.

Screenshots are written to storage/qa/. Core Web Vitals field targets require production traffic; local screenshots and response times do not establish those targets.
