#!/bin/bash
echo ""
echo "  LIMPIA OESTE - Deploy a Producción"
echo "  ===================================="
echo ""

echo "[1/2] Exportando base de datos..."
php db_export.php
echo ""

echo "[2/2] Subiendo archivos por FTP..."
php deploy.php
echo ""
