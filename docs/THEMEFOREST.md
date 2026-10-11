# ThemeForest research

Checked on 11 October 2026. Nothing here was purchased or copied into FleetPilot. Prices are the regular ThemeForest license price shown on the product page that day (quality check, future updates, and 6 months of support). Extended-license prices were not listed as a separate verified figure, so they are not invented here.

FleetPilot is Laravel, Inertia, React, and TypeScript. None of these templates drop into that stack. The recommendation is to keep the original FleetPilot interface. If a visual reference is approved later, Parcelix is the closest of the three, and it would still be a reference rather than an install.

## Parcelix

[Parcelix – Courier & Parcel Delivery Dashboard Admin HTML Template](https://themeforest.net/item/parcelix-courier-parcel-delivery-dashboard-admin-html-template/64062697) by UIAXIS. **$29**.

- Stack: HTML, Tailwind CSS 4, JavaScript, Webpack. The listing does not include Laravel, Inertia, React, or TypeScript.
- Responsive, with light and dark layouts saved in localStorage.
- Last update on the product page: 16 July 2026.
- Useful reference for shipment, rider, fleet, and warehouse pages.
- Not compatible as an install. Map and rider-tracking art in the demo must not be treated as a live GPS backend.

## Taxi CRM

[Taxi CRM - Bootstrap 5 HTML Admin Dashboard Template](https://themeforest.net/item/taxi-crm-bootstrap-5-html-admin-dashboard-template/63964244) by PVR_TECH_STUDIO. **$24**.

- Stack: Bootstrap 5, HTML, jQuery. Dense dispatch, driver, vehicle, and trip pages.
- Responsive, with a light and dark toggle.
- Last update on the product page: 7 July 2026.
- Chart pack includes Highcharts and amCharts, which carry their own license limits.
- The live demo promotes a dispatch map. FleetPilot must not present that as route optimization or live GPS.
- Not an Inertia or Laravel template.

## Cargomax

[Cargomax – Shipping & Logistics Admin Dashboard Next.js Template](https://themeforest.net/item/cargomax-shipping-logistics-admin-dashboard-nextjs-template/58874766) by pixelaxis. **$49**.

- Stack: Next.js 15, React 19, Tailwind CSS, TypeScript, and a light/dark toggle. The author says it is not a plain HTML template.
- Responsive.
- Last update on the product page: 29 July 2025.
- Closest framework cousin, and still the wrong application model: Next.js app router rather than Laravel + Inertia.
- Shipment-map UI in the listing is a frontend. It is not a tracking or routing service.

## Recommendation

Do not buy a template for this repository unless you explicitly approve a purchase. The three real candidates above were compared for dispatch density, mobile layout, light/dark support, and whether the license and framework could legally and technically land in this codebase. Parcelix is the one to use as a visual reference if you later want a purchased mood board. Taxi CRM is the most operations-dense, and the least compatible. Cargomax shares React and TypeScript, and it would still be a rewrite to fit Inertia.
