docker run -d --name vecaku_diena -p 8080:80 -e pass=RZG1L0ve -e teachers_pass=Jurmala\<3 -e DATABASE_URL=mysql://root:rootpassword@35.228.250.24/testdb artik292/vecaku-diena:20241030_003

docker exec vecaku_diena /usr/bin/mysqldump -u root --password=root DATABASE > backup.sql

2024:
8012 visitors
471 records from 1020 available