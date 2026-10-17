# Day-wise school gender report

Open the event and choose **Schedule / Scheduling → Day-wise participants** in either the Sahodaya or State portal. Schedule pages link to this separate report page and no longer load the participant summary themselves.

Select all days, an assigned day, the whole event or entries awaiting a date. Each school occupies one row. Dates form grouped column headers with Male, Female and Total beneath each date and an All schools totals row. There is no combined overall column across dates. Other and Unspecified columns appear only when the selected dates contain those records. The PDF uses landscape A4, splitting long date ranges into readable tables without repeating a combined overall count. Excel, PDF preview and PDF download use the selected scope and exclude student names and identifiers. Overall totals deduplicate participants across days rather than adding daily counts. Existing active/approved registration, standby, withdrawal and disqualification rules remain in force.

Sahodaya gender comes from the student/teacher record; State gender comes from participant metadata. Missing or unrecognised gender is unspecified. Conflicting known genders for the same stable identity within a scope are also unspecified; a missing value does not override a known value. No gender is inferred from a name. State legacy records without gender remain unspecified until corrected.

The page and export routes retain schedule permissions and event/tenant ownership checks. The page payload omits student names and identifiers.
