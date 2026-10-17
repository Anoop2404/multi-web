package admin

import (
	"regexp"
	"strings"
)

var (
	reEdge    = regexp.MustCompile(`Edg(?:e|A|iOS)?/(\d+)`)
	reOpera   = regexp.MustCompile(`OPR/(\d+)`)
	reSamsung = regexp.MustCompile(`SamsungBrowser/(\d+)`)
	reChrome  = regexp.MustCompile(`(?:Chrome|CriOS)/(\d+)`)
	reFirefox = regexp.MustCompile(`(?:Firefox|FxiOS)/(\d+)`)
	reSafari  = regexp.MustCompile(`Version/(\d+)[.\d]* .*Safari/`)
)

// DescribeUA turns a user agent into "Chrome 131 / Android" for the monitor.
func DescribeUA(ua string) string {
	if ua == "" {
		return ""
	}
	browser := ""
	for _, c := range []struct {
		re   *regexp.Regexp
		name string
	}{{reEdge, "Edge"}, {reOpera, "Opera"}, {reSamsung, "Samsung Internet"}, {reFirefox, "Firefox"}, {reChrome, "Chrome"}, {reSafari, "Safari"}} {
		if m := c.re.FindStringSubmatch(ua); m != nil {
			browser = c.name + " " + m[1]
			break
		}
	}
	if browser == "" {
		browser = "Browser"
	}
	os := "Other"
	switch {
	case strings.Contains(ua, "Android"):
		os = "Android"
	case strings.Contains(ua, "iPhone"), strings.Contains(ua, "iPad"), strings.Contains(ua, "iPod"):
		os = "iOS"
	case strings.Contains(ua, "CrOS"):
		os = "ChromeOS"
	case strings.Contains(ua, "Windows"):
		os = "Windows"
	case strings.Contains(ua, "Mac OS X"), strings.Contains(ua, "Macintosh"):
		os = "macOS"
	case strings.Contains(ua, "Linux"):
		os = "Linux"
	}
	return browser + " / " + os
}
