#!/bin/bash

# ArtLoop - Startup Script
# This script automates the setup and launch of the ArtLoop web application

# ANSI color codes for better readability
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Print banner
echo -e "${BLUE}"
echo "    _         _   _                       "
echo "   / \   _ __| |_| |    ___   ___  _ __  "
echo "  / _ \ | '__| __| |   / _ \ / _ \| '_ \ "
echo " / ___ \| |  | |_| |__| (_) | (_) | |_) |"
echo "/_/   \_\_|   \__|_____\___/ \___/| .__/ "
echo "                                  |_|    "
echo -e "${NC}"
echo "Digital Art Gallery Web Application"
echo "====================================="
echo ""

# Function to check if a command exists
command_exists() {
    command -v "$1" >/dev/null 2>&1
}

# Check prerequisites
echo -e "${YELLOW}Checking prerequisites...${NC}"

# Check for PHP
if command_exists php; then
    PHP_VERSION=$(php -r 'echo PHP_VERSION;')
    echo -e "✅ PHP is installed (version $PHP_VERSION)"
else
    echo -e "${RED}❌ PHP is not installed. Please install PHP 7.4 or higher.${NC}"
    exit 1
fi

# Check for MySQL
if command_exists mysql; then
    MYSQL_VERSION=$(mysql --version | grep -oE '[0-9]+\.[0-9]+\.[0-9]+')
    echo -e "✅ MySQL is installed (version $MYSQL_VERSION)"
else
    echo -e "${RED}❌ MySQL is not installed. Please install MySQL 5.7 or higher.${NC}"
    exit 1
fi

echo ""

# Create uploads directory if it doesn't exist
if [ ! -d "uploads" ]; then
    echo -e "${YELLOW}Creating uploads directory...${NC}"
    mkdir -p uploads
    echo -e "✅ Created uploads directory"
fi

# Set permissions for uploads directory
echo -e "${YELLOW}Setting permissions for uploads directory...${NC}"
chmod 755 uploads
echo -e "✅ Permissions set"

echo ""

# Database setup
echo -e "${YELLOW}Database Setup${NC}"
echo "Would you like to set up the database? (y/n)"
read -r setup_db

if [ "$setup_db" = "y" ] || [ "$setup_db" = "Y" ]; then
    echo "Enter your MySQL username (default: root):"
    read -r db_user
    db_user=${db_user:-root}

    echo "Enter your MySQL password (leave empty if none):"
    read -rs db_pass

    # Update database connection file
    echo -e "${YELLOW}Updating database connection settings...${NC}"

    # Create a backup of the original file
    cp includes/db_connect.php includes/db_connect.php.bak

    # Update the file with new credentials using a cross-platform compatible approach
    # Create a temporary file
    cat includes/db_connect.php | 
    sed "s/\$host = '.*';/\$host = '127.0.0.1';/" |
    sed "s/\$username = '.*';/\$username = '$db_user';/" | 
    sed "s/\$password = '.*';/\$password = '$db_pass';/" > includes/db_connect.php.tmp

    # Replace the original file with the temporary file
    mv includes/db_connect.php.tmp includes/db_connect.php

    echo -e "✅ Database connection settings updated"

    # Check if MySQL server is running and accessible
    echo -e "${YELLOW}Checking MySQL server connection...${NC}"
    if [ -z "$db_pass" ]; then
        mysql -h 127.0.0.1 -u "$db_user" --connect-timeout=5 -e "SELECT 1" >/dev/null 2>&1
    else
        mysql -h 127.0.0.1 -u "$db_user" -p"$db_pass" --connect-timeout=5 -e "SELECT 1" >/dev/null 2>&1
    fi

    if [ $? -ne 0 ]; then
        echo -e "${RED}❌ Cannot connect to MySQL server on 127.0.0.1:3306.${NC}"
        echo -e "${YELLOW}Possible reasons:${NC}"
        echo "  1. MySQL server is not running. Start it using your system's service manager."
        echo "  2. MySQL server is not accepting connections on 127.0.0.1:3306."
        echo "  3. Firewall is blocking the connection."
        echo "  4. MySQL credentials are incorrect."
        echo ""
        echo "Please ensure MySQL server is running and accessible, then try again."
        echo "You can try importing the schema manually using:"
        echo "mysql -h 127.0.0.1 -u username -p artloop < database/schema.sql"
        exit 1
    else
        echo -e "✅ MySQL server is accessible"
    fi

    # Import database schema
    echo -e "${YELLOW}Importing database schema...${NC}"
    if [ -z "$db_pass" ]; then
        mysql -h 127.0.0.1 -u "$db_user" < database/schema.sql
    else
        mysql -h 127.0.0.1 -u "$db_user" -p"$db_pass" < database/schema.sql
    fi

    if [ $? -eq 0 ]; then
        echo -e "✅ Database schema imported successfully"
    else
        echo -e "${RED}❌ Failed to import database schema. Please check your MySQL credentials.${NC}"
        echo "You can try importing the schema manually using:"
        echo "mysql -h 127.0.0.1 -u username -p artloop < database/schema.sql"
    fi
else
    echo -e "${YELLOW}Skipping database setup.${NC}"
    echo "Make sure your database is properly configured before using the application."
fi

echo ""

# Start the web server
echo -e "${YELLOW}Starting the web server...${NC}"
echo "Select a port to run the server (default: 8000):"
read -r port
port=${port:-8000}

echo -e "${GREEN}Starting PHP development server on port $port...${NC}"
echo -e "${GREEN}Press Ctrl+C to stop the server${NC}"
echo ""
echo -e "${BLUE}Access the application at: http://localhost:$port${NC}"
echo -e "${BLUE}Default admin credentials:${NC}"
echo -e "${BLUE}  Username: admin${NC}"
echo -e "${BLUE}  Password: admin123${NC}"
echo ""

# Start the PHP development server
php -S "localhost:$port"
