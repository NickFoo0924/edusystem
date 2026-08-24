<?xml version="1.0" encoding="UTF-8"?>
<!--
    analytics-chart.xsl — turns the analytics document into an SVG line chart.

    SVG is an XML vocabulary, so this is a genuine XML-to-XML transformation
    rather than a template that happens to emit angle brackets.

    XSLT 1.0, which is what PHP's libxslt implements. Everything used here is
    from Chapter 4B: xsl:stylesheet, xsl:template match, xsl:for-each with an
    XPath select, xsl:value-of reading both child elements and @attributes,
    and count(). No XSLT 2.0 functions — libxslt would fail on them.

    The geometry is computed here, from the data, not handed over as pixel
    values by PHP. Each point's x comes from its position in the series and y
    from its @average, so the stylesheet is doing the work.

    THE NAMESPACE MATTERS. The svg element below carries
    xmlns="http://www.w3.org/2000/svg". Without it a browser renders an empty
    box, with nothing in the console to say why.
-->
<xsl:stylesheet version="1.0"
                xmlns:xsl="http://www.w3.org/1999/XSL/Transform">

    <xsl:output method="xml" indent="yes" omit-xml-declaration="yes"/>

    <!--
        Plot geometry. Declared once here so the drawing templates below read
        as arithmetic on named quantities rather than a scatter of magic
        numbers. The viewBox is 760x360 and the plot area sits inside it.
    -->
    <xsl:variable name="chartWidth" select="760"/>
    <xsl:variable name="chartHeight" select="360"/>
    <xsl:variable name="left" select="56"/>
    <xsl:variable name="right" select="700"/>
    <xsl:variable name="top" select="40"/>
    <xsl:variable name="bottom" select="290"/>
    <xsl:variable name="plotWidth" select="$right - $left"/>
    <xsl:variable name="plotHeight" select="$bottom - $top"/>

    <!-- Tailwind's palette, matching the rest of the page. -->
    <xsl:variable name="axisColour" select="'#9ca3af'"/>
    <xsl:variable name="gridColour" select="'#e5e7eb'"/>
    <xsl:variable name="textColour" select="'#6b7280'"/>
    <xsl:variable name="titleColour" select="'#111827'"/>

    <!-- ==========================================================
         Entry point
         ========================================================== -->
    <xsl:template match="/">
        <svg xmlns="http://www.w3.org/2000/svg"
             viewBox="0 0 {$chartWidth} {$chartHeight}"
             preserveAspectRatio="xMidYMid meet"
             role="img"
             aria-label="Average cohort completion over time, by course"
             style="width: 100%; height: auto;">

            <text x="{$left}" y="24" font-family="system-ui, sans-serif"
                  font-size="15" font-weight="600" fill="{$titleColour}">
                Cohort completion over time
            </text>

            <xsl:choose>
                <!--
                    Nothing has been recorded anywhere yet. Say so plainly
                    rather than drawing an empty pair of axes, which reads as
                    a fault.
                -->
                <xsl:when test="count(//point) = 0">
                    <text x="{$chartWidth div 2}" y="{$chartHeight div 2}"
                          text-anchor="middle" font-family="system-ui, sans-serif"
                          font-size="13" fill="{$textColour}">
                        No progress recorded yet — the chart appears once work has been graded.
                    </text>
                </xsl:when>

                <xsl:otherwise>
                    <xsl:call-template name="grid"/>
                    <xsl:call-template name="axes"/>

                    <!-- One line per course that actually has readings. -->
                    <xsl:for-each select="//course[count(point) &gt; 0]">
                        <xsl:call-template name="series"/>
                    </xsl:for-each>

                    <xsl:call-template name="legend"/>
                </xsl:otherwise>
            </xsl:choose>
        </svg>
    </xsl:template>

    <!-- ==========================================================
         Gridlines and the percentage scale, every 25%
         ========================================================== -->
    <xsl:template name="grid">
        <xsl:call-template name="gridline"><xsl:with-param name="value" select="0"/></xsl:call-template>
        <xsl:call-template name="gridline"><xsl:with-param name="value" select="25"/></xsl:call-template>
        <xsl:call-template name="gridline"><xsl:with-param name="value" select="50"/></xsl:call-template>
        <xsl:call-template name="gridline"><xsl:with-param name="value" select="75"/></xsl:call-template>
        <xsl:call-template name="gridline"><xsl:with-param name="value" select="100"/></xsl:call-template>
    </xsl:template>

    <xsl:template name="gridline">
        <xsl:param name="value"/>
        <!-- y grows downward in SVG, so a high percentage is a low y. -->
        <xsl:variable name="y" select="$bottom - ($value div 100) * $plotHeight"/>

        <line x1="{$left}" y1="{$y}" x2="{$right}" y2="{$y}"
              stroke="{$gridColour}" stroke-width="1"/>

        <text x="{$left - 10}" y="{$y + 4}" text-anchor="end"
              font-family="system-ui, sans-serif" font-size="11" fill="{$textColour}">
            <xsl:value-of select="$value"/>%
        </text>
    </xsl:template>

    <!-- ==========================================================
         Axes, with the first and last date labelled
         ========================================================== -->
    <xsl:template name="axes">
        <line x1="{$left}" y1="{$top}" x2="{$left}" y2="{$bottom}"
              stroke="{$axisColour}" stroke-width="1.5"/>
        <line x1="{$left}" y1="{$bottom}" x2="{$right}" y2="{$bottom}"
              stroke="{$axisColour}" stroke-width="1.5"/>

        <text x="{$left}" y="{$bottom + 20}" font-family="system-ui, sans-serif"
              font-size="11" fill="{$textColour}">
            <xsl:value-of select="//course[count(point) &gt; 0][1]/point[1]/@date"/>
        </text>

        <text x="{$right}" y="{$bottom + 20}" text-anchor="end"
              font-family="system-ui, sans-serif" font-size="11" fill="{$textColour}">
            <xsl:value-of select="//course[count(point) &gt; 0][1]/point[last()]/@date"/>
        </text>

        <text x="{($left + $right) div 2}" y="{$bottom + 20}" text-anchor="middle"
              font-family="system-ui, sans-serif" font-size="11" fill="{$textColour}">
            Date recorded
        </text>

        <!-- Rotated about its own position, so it reads up the y axis. -->
        <text x="16" y="{($top + $bottom) div 2}" text-anchor="middle"
              font-family="system-ui, sans-serif" font-size="11" fill="{$textColour}"
              transform="rotate(-90 16 {($top + $bottom) div 2})">
            Average completion
        </text>
    </xsl:template>

    <!-- ==========================================================
         One course: its polyline, its points, and the final value
         ========================================================== -->
    <xsl:template name="series">
        <xsl:variable name="colour">
            <xsl:call-template name="colourFor">
                <xsl:with-param name="index" select="position()"/>
            </xsl:call-template>
        </xsl:variable>
        <xsl:variable name="count" select="count(point)"/>

        <polyline fill="none" stroke="{$colour}" stroke-width="2"
                  stroke-linejoin="round" stroke-linecap="round">
            <xsl:attribute name="points">
                <xsl:for-each select="point">
                    <!--
                        x spreads the readings evenly across the plot; a single
                        reading is placed at the left edge rather than dividing
                        by zero.
                    -->
                    <xsl:choose>
                        <xsl:when test="$count = 1">
                            <xsl:value-of select="$left"/>
                        </xsl:when>
                        <xsl:otherwise>
                            <xsl:value-of select="$left + (position() - 1) * ($plotWidth div ($count - 1))"/>
                        </xsl:otherwise>
                    </xsl:choose>
                    <xsl:text>,</xsl:text>
                    <xsl:value-of select="$bottom - (@average div 100) * $plotHeight"/>
                    <xsl:text> </xsl:text>
                </xsl:for-each>
            </xsl:attribute>
        </polyline>

        <!-- A marker on each reading, and the value on the last one. -->
        <xsl:for-each select="point">
            <xsl:variable name="x">
                <xsl:choose>
                    <xsl:when test="$count = 1"><xsl:value-of select="$left"/></xsl:when>
                    <xsl:otherwise>
                        <xsl:value-of select="$left + (position() - 1) * ($plotWidth div ($count - 1))"/>
                    </xsl:otherwise>
                </xsl:choose>
            </xsl:variable>
            <xsl:variable name="y" select="$bottom - (@average div 100) * $plotHeight"/>

            <circle cx="{$x}" cy="{$y}" r="2.5" fill="{$colour}"/>

            <!--
                The last reading gets a larger marker, but its value is printed
                in the legend rather than beside the line. Two courses that both
                finish at 100% end at the same coordinates, and labels drawn
                there would sit exactly on top of one another.
            -->
            <xsl:if test="position() = last()">
                <circle cx="{$x}" cy="{$y}" r="4" fill="{$colour}"/>
            </xsl:if>
        </xsl:for-each>
    </xsl:template>

    <!-- ==========================================================
         Legend — one entry per plotted course
         ========================================================== -->
    <xsl:template name="legend">
        <xsl:for-each select="//course[count(point) &gt; 0]">
            <xsl:variable name="colour">
                <xsl:call-template name="colourFor">
                    <xsl:with-param name="index" select="position()"/>
                </xsl:call-template>
            </xsl:variable>
            <!-- Three per row, wrapping downward. -->
            <xsl:variable name="column" select="(position() - 1) mod 3"/>
            <xsl:variable name="row" select="floor((position() - 1) div 3)"/>
            <xsl:variable name="x" select="$left + $column * 220"/>
            <xsl:variable name="y" select="$bottom + 46 + $row * 18"/>

            <rect x="{$x}" y="{$y - 8}" width="10" height="10" rx="2" fill="{$colour}"/>
            <text x="{$x + 16}" y="{$y}" font-family="system-ui, sans-serif"
                  font-size="11" fill="{$textColour}">
                <xsl:value-of select="@code"/>
                <xsl:text> · </xsl:text>
                <xsl:value-of select="@students"/>
                <xsl:text> students · </xsl:text>
                <!-- The course's most recent reading, which is where its line
                     ends on the plot. -->
                <tspan font-weight="600" fill="{$colour}">
                    <xsl:value-of select="format-number(point[last()]/@average, '0.#')"/>
                    <xsl:text>%</xsl:text>
                </tspan>
            </text>
        </xsl:for-each>
    </xsl:template>

    <!--
        Series colours, cycling through six of Tailwind's 600-weight hues so
        the chart sits with the rest of the page.
    -->
    <xsl:template name="colourFor">
        <xsl:param name="index"/>
        <xsl:variable name="slot" select="($index - 1) mod 6"/>
        <xsl:choose>
            <xsl:when test="$slot = 0">#2563eb</xsl:when>
            <xsl:when test="$slot = 1">#059669</xsl:when>
            <xsl:when test="$slot = 2">#d97706</xsl:when>
            <xsl:when test="$slot = 3">#7c3aed</xsl:when>
            <xsl:when test="$slot = 4">#dc2626</xsl:when>
            <xsl:otherwise>#0891b2</xsl:otherwise>
        </xsl:choose>
    </xsl:template>

</xsl:stylesheet>
