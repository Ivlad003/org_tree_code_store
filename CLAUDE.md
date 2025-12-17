# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a simple static site that displays an organizational chart. The entire application consists of a single `index.html` file that embeds an org chart visualization via iframe from `orgchart.hexviz.com`.

## Architecture

- **Data Source**: Google Sheets CSV (published to web)
- **Visualization**: hexviz.com org chart renderer embedded via iframe
- **Configuration**: Chart styling and layout options are URL-encoded in the iframe `src` attribute

## Development

No build process required. Open `index.html` directly in a browser or serve with any static file server.

To modify the org chart configuration, decode the URL parameters in the iframe `src`, edit the JSON config, and re-encode.
