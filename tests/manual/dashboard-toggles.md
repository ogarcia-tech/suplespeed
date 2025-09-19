# Dashboard Toggle Verification

## Goal
Confirm that enabling or disabling the **WebP/AVIF Conversion** toggle in the settings screen is reflected in the **Modern Formats** status badge inside the dashboard.

## Prerequisites
- WordPress admin access with the Suple Speed plugin activated.
- Ability to visit both the Suple Speed **Settings** and **Dashboard** pages.

## Steps
1. Navigate to **Settings → Suple Speed** and open the **Images** tab.
2. Locate the **WebP/AVIF Conversion** toggle and set it to **Enabled**.
3. Click **Save Changes**.
4. Open **Dashboard → Suple Speed** (refresh if already open).
5. Verify that, under **Images → Optimization Status**, the **Modern Formats** badge shows **Enabled**.
6. Return to the **Images** tab in the settings, disable the **WebP/AVIF Conversion** toggle, and save.
7. Reload the Suple Speed dashboard and confirm the **Modern Formats** badge now shows **Disabled**.

## Expected Result
The dashboard immediately reflects the toggle state after saving the settings, showing **Enabled** when the toggle is on and **Disabled** when it is off.
