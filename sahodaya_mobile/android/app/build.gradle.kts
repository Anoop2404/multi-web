plugins {
    id("com.android.application")
    id("dev.flutter.flutter-gradle-plugin")
}

// White-label flavors — add one block per Sahodaya (see tenants/README.md).
val tenantFlavors = mapOf(
    "malappuram" to Pair(
        "org.malappuramcentralsahodaya.mobile",
        "Malappuram Central Sahodaya",
    ),
    // "kozhikode" to Pair("org.kozhikodesahodaya.mobile", "Kozhikode Sahodaya"),
)

android {
    namespace = "com.sahodaya.mobile"
    compileSdk = 36
    ndkVersion = flutter.ndkVersion

    compileOptions {
        sourceCompatibility = JavaVersion.VERSION_17
        targetCompatibility = JavaVersion.VERSION_17
    }

    flavorDimensions += "tenant"

    productFlavors {
        tenantFlavors.forEach { (slug, config) ->
            create(slug) {
                dimension = "tenant"
                applicationId = config.first
                resValue("string", "app_name", config.second)
            }
        }
    }

    defaultConfig {
        minSdk = flutter.minSdkVersion
        targetSdk = 36
        versionCode = flutter.versionCode
        versionName = flutter.versionName
    }

    buildTypes {
        release {
            signingConfig = signingConfigs.getByName("debug")
        }
    }
}

kotlin {
    compilerOptions {
        jvmTarget = org.jetbrains.kotlin.gradle.dsl.JvmTarget.JVM_17
    }
}

flutter {
    source = "../.."
}
