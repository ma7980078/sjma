<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <!-- import CSS -->
    <link rel="stylesheet" href="https://unpkg.com/element-ui/lib/theme-chalk/index.css">
</head>
<body>
<div id="app">
    <el-container>
        <el-header>上传图片</el-header>
        <el-header>注意（命名格式：<el-tag type="warning">车型_颜色.格式后缀</el-tag> 示例：<el-tag type="warning">Mana 850_黑色_logo.jpeg 或 Mana 850_黑色_细节.jpeg</el-tag>）（支持图片格式：<el-tag type="warning">.jpeg 或 .jpg 或 .png</el-tag>）</el-header>
        <el-main>
            <el-upload
                    action="/moto/upload/file_v2"
                    :auto-upload="false"
                    :multiple="multiple"
                    name="file"
                    list-type="picture"
                    header="multipart/form-data"
                    class="upload-demo"
                    ref="upload"
                    :on-preview="handlePreview"
                    :on-remove="handleRemove"
                    :on-success="returnFunc"
                    :file-list="fileList">
                <el-button slot="trigger" size="small" type="primary">选取文件</el-button>
                <el-button style="margin-left: 10px;" size="small" type="success" @click="submitUpload">上传到服务器</el-button>
            </el-upload>
        </el-main>
    </el-container>
</div>
</body>
<!-- import Vue before Element -->
<script src="https://unpkg.com/vue/dist/vue.js"></script>
<!-- import JavaScript -->
<script src="https://unpkg.com/element-ui/lib/index.js"></script>
<script>
    new Vue({
        el: '#app',
        data: function() {
            return {
                fileList: [],
                multiple: true,
                switch_value: false,
                opera: {
                    type: "low"
                }
            }
        },
        methods: {
            submitUpload() {
                this.$refs.upload.submit()

            },
            handleRemove(file, fileList) {
                console.log(file, fileList);
            },
            handlePreview(file) {
                console.log(file);
            },
            returnFunc(res) {
                if (res.code === 200) {
                    this.$message.success(res.name + ' ' + res.message)
                } else {
                    this.$message.error(res.name + ' ' + res.message)
                }


            }
        }
    })
</script>
</html>

<style>
    .el-header, .el-footer {
        background-color: #B3C0D1;
        color: #333;
        text-align: center;
        line-height: 60px;
    }

    .el-aside {
        background-color: #D3DCE6;
        color: #333;
        text-align: center;
        line-height: 200px;
    }

    .el-main {
        background-color: #E9EEF3;
        color: #333;
        text-align: center;
        line-height: 160px;
    }

    body > .el-container {
        margin-bottom: 40px;
    }

    .el-container:nth-child(5) .el-aside,
    .el-container:nth-child(6) .el-aside {
        line-height: 260px;
    }

    .el-container:nth-child(7) .el-aside {
        line-height: 320px;
    }
</style>
